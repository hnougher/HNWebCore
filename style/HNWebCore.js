
// From developer.mozilla.org
if (!Date.now) {
  Date.now = function now() {
    return new Date().getTime();
  };
}

var doASAP;
(function(){
	var timer = null;
	var sliceTime = 15; // ms
	var queue = [];
	
	/** Queues a callback and its parameters for execution asap without
	* holding up the browser for too long.
	* @param function func The function to run.
	* @param function runInside The function the func should have as 'this'.
	* @param array args The arguments to be passed the the function.
	*/
	doASAP = function(func, runInside, args) {
		queue.push(func, runInside, args);
		if (timer == null) timer = setInterval(processor, sliceTime);
	}
	
	/** To be called by the timer or other event trigger */
	function processor() {
		var endAfter = Date.now() + sliceTime;
		while (queue.length > 0 && Date.now() > endAfter)
			queue.shift().apply(queue.shift(), queue.shift());
		if (queue.length == 0) {
			clearInterval(timer);
			timer = null;
		}
	}
})();

var HNOBJBasic, HNOBJLoad, OBJClasses = {};
(function(){
	// Constants
	var NOT_LOADED = 0;
	var NO_RECORD = 1;
	var LOADED = 2;
	
	/** Stats collection vars */
	var cacheGetTotal = 0;
	var cacheGetHits = 0;
	var cacheSetTotal = 0;
	var cacheSetHits = 0;
	var cacheRemTotal = 0;
	var totalCreated = [];
	var totalDestroyed = [];
	
	/** OBJ cache */
	var cache = [];
	
	// ############# START HNOBJLOAD ############
	HNOBJLoad = function(OBJType, id, loadNow) {
		var OBJ = HNOBJLoad.cacheGet(OBJType, id);
		if (OBJ === false)
			OBJ = new HNOBJBasic(OBJType, id, loadNow);
		return OBJ;
	}
	HNOBJLoad.cacheGet = function(OBJType, id) {
		cacheGetTotal++;
		if (OBJType in cache && id in cache[OBJType]) {
			cacheGetHits++;
			return cache[OBJType][id];
		}
		return false;
	}
	HNOBJLoad.cacheSet = function(OBJ) {
		cacheSetTotal++;
		if (!(OBJ instanceof HNOBJBasic))
			throw 'OBJ is not of type HNOBJBasic';
		var OBJType = OBJ.OBJType;
		if (OBJ.id == [0])
			throw 'An OBJ with id 0 cannot be cached';
		if (!(OBJType in cache))
			cache[OBJType] = [];
		cache[OBJType][OBJ.id] = OBJ;
		cacheSetHits++;
	}
	HNOBJLoad.cacheRem = function(OBJ) {
		cacheRemTotal++;
		if (!(OBJ instanceof HNOBJBasic))
			throw 'OBJ is not of type HNOBJBasic';
		var OBJType = OBJ.OBJType;
		if (OBJType in cache && OBJ.id in cache[OBJType]) {
			delete cache[OBJType][OBJ.id];
	}
	// ############# END HNOBJLOAD ############
	
	// ############# START HNOBJBASIC ############
	HNOBJBasic = function(OBJType, id, loadNow) {
		HNOBJLoad.cacheSet(this);
		if (!(id instanceof Array))
			throw 'OBJ id must be an array';
		
		// Private or Protected
		/** Contains the id for the id field of this object. */
		this.id = id;
		
		/** Contains the OBJ type that this was created with. */
		this.OBJType = OBJType;
		
		/** Stores the current status of this OBJ.
		* This property should always be checked by using has_record or checkLoaded
		*/
		this.status = NOT_LOADED;
		
		/** This contains the same data that the Database does. */
		this.data = {};
		
		/** Contains a waiting list of callbacks waiting for the data to arrive. */
		this.dataCallbacks = [];
		
		if (OBJType in OBJClasses) {
			// Has extra OBJ specific functions
			for (var f in OBJClasses[OBJType])
				this[f] = OBJClasses[OBJType][f];
		}
		
		if (loadNow) this.load();
	}
	
	HNOBJBasic.prototype = {
		/** Checks if this object is loaded.
		* @returns boolean True if this OBJ is currently loaded */
		isLoaded: function() {
			return (this.status != NOT_LOADED)
		},
		
		/** This is used to register a callback that will be called once
		* when the data for the current OBJ has finished loading.
		* @note For a given OBJ the callbacks should always be run in the order
		*     as this withData function was called.
		* @param function callback The function to call when the data is available.
		*     The first and only parameter is an object of loaded data.
		*/
		withData: function(callback){
			if (this.isLoaded()) {
				doASAP(callback, this, this.data);
			} else {
				this.dataCallbacks.push(callback);
				this.load();
			}
		},
		
		/**
		* @param boolean restart If this is true and the OBJ is already loading then
		*    the data loading will be started again.
		*/
		load: function(restart) {
			var self = this;
			jQuery.post(HNOBJ_WORKERURL, param)
				.success(function(data, textStatus, jqXHR){
					self.loadComplete(data, textStatus, jqXHR);
				})
				.error(function(data, textStatus, jqXHR){
					self.loadError(data, textStatus, jqXHR);
				});
		},
		
		loadError: function(data, textStatus, jqXHR) {
		},
		
		loadComplete: function(data, textStatus, jqXHR) {
			while (this.dataCallbacks.length > 0)
				doASAP(this.dataCallbacks.shift(), this, this.data);
		},
		
		save: function() {
			throw 'Not implemented';
		},
		
		remove: function() {
			throw 'Not implemented';
		},
	}
	// ############# END HNOBJBASIC ############
})();


// Playbits
OBJClasses.user = {
	Authenticate: function(username, password) {
		// @TODO: call authenticate on the PHP class
	}
}
