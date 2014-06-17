
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
			throw new Exception('OBJ is not of type HNOBJBasic');
		var OBJType = OBJ.OBJType;
		var id = OBJ.getId();
		if (id == 0)
			throw new Exception('An OBJ with id 0 cannot be cached');
		if (!(OBJType in cache))
			cache[OBJType] = [];
		cache[OBJType][id] = OBJ;
		cacheSetHits++;
	}
	HNOBJLoad.cacheRem = function(OBJ) {
		cacheRemTotal++;
		if (!(OBJ instanceof HNOBJBasic))
			throw new Exception('OBJ is not of type HNOBJBasic');
		var OBJType = OBJ.OBJType;
		var id = OBJ.getId();
		if (OBJType in cache && id in cache[OBJType]) {
			delete cache[OBJType][id];
	}
	// ############# END HNOBJLOAD ############
	
	// ############# START HNOBJBASIC ############
	HNOBJBasic = function(OBJType, id, loadNow) {
		HNOBJLoad.cacheSet(this);
		this.__initialise();
		this.__construct(OBJType, id);
		if (loadNow) this.load();
	}
	
	HNOBJBasic.prototype = {
		__initialise: function() {
			// Private or Protected
			/** Will contain the id for the id field of this object. */
			this.myId;
			
			/** Contains the OBJ type that this was created with. */
			this.OBJType;
			
			/** Stores the current status of this OBJ.
			* This property should always be checked by using has_record or checkLoaded
			*/
			this.status = NOT_LOADED;
			
			/** This contains the same data that the Database does. */
			this.myData = [];
			
			/** Contains the values that have been changed compared to the database values. */
			this.myChangedData = [];
		},
		
		__construct: function(OBJType, id) {
			if (OBJType in OBJClasses) {
				// Has extra OBJ specific functions
				for (var f in OBJClasses[OBJType])
					this[f] = OBJClasses[OBJType][f];
			}
			
			this.myId = id;
			this.OBJType = OBJType;
		},
		
		load: function load() {
		}
	}
	// ############# END HNOBJBASIC ############
})();

var HNOBJCache;
(function(){
	
	/** This is an array of objects and prototypes that have already been loaded */
	var cachedObjects = [];
	
	HNOBJCache = {
		getCacheCounts: function() {
			return [getTotal, getHits, setTotal, setHits, remTotal];
		},
		
		/** Checks if an OBJ is in the cache for given OBJType and id array */
		get: function(OBJType, id) {
		}
	}
})();

// Playbits
OBJClasses.user = {
	Authenticate: function(username, password) {
		// @TODO: call authenticate on the PHP class
	}
}
