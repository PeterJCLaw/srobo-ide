dojo.require("dojox.gfx");

// SimTab: if we ever move to multiple simulation tabs SimTab will handle each individual tab,
//		and will have a Simulation object attached to each one, with (potentially) a single SimPage

// The simulator page, deals with the "physical" presence in the web browser
function SimPage() {
	// Member functions:

	// Public functions:
	//  - load: creates and loads a simulation for the page
	//  - close: performs checks/saves and then calls _close

	// Private functions:
	//  - _init: called on instantiation
	//  - _close: destroys simulation, closes tab and disconnects signals

	// Public properties:
	//  - tab: the Tab object associated with the page


	// Private properties:
	//  - _initted:	true once the tab has been successfully initialised
	//  - _surface: the dojo.gfx surface for Simulations to draw on
	//  - _sim: the (currently single) simulation we can have attached to the page

	//the tab for the page
	this.tab = null;
	// Whether _init has run
	this._initted = false;
	// the Simulation object
	this._sim = null;


	// Initialise the simulator page -- but don't show it
	this._init = function() {
		if( this._initted )
			return;

		this.tab = new Tab( "Simulator" );
		connect( this.tab, "onfocus", bind( simpage.show, simpage ) );
		connect( this.tab, "onblur", bind( simpage.hide, simpage ) );
		connect( this.tab, "onclickclose", bind( this.close, this ) );
		tabbar.add_tab( this.tab );

		var container = $("graphics");		// get the div
		this._surface = dojox.gfx.createSurface(container, 640, 640);


		logDebug( "Simulator page initialised" );
		this._initted = true;
	}

	// public function performs necessary checks then calls this._close
	this.close = function() {
		this._close();
	}

	// internal function actually closes tab
	this._close = function() {
		logDebug("closing simulator tab");
		if(!this._initted)
			return;
		this._sim.pause();
		this._sim.destroy();
		this.tab.close();
		disconnectAll(this);
		this._initted = false;
	}

	// Load a simulation
	this.load = function(project) {
		this._init();		// in case it hasn't been initted already
		tabbar.switch_to(this.tab);
		logDebug( "Simulating the "+project+" project" );

		this._sim = new Simulation();		// create empty simulation
		this._sim.attach(this._surface);			// attach to page elements
		this._sim.load();

	}

	// Unhide the simulator page
	this.show = function() {
		logDebug( "Showing the simulator page" );
		this._init();
		setStyle('simulator-page', {'display':'block'});
	}

	// Hide the simulator page
	this.hide = function() {
		logDebug( "Hiding the simulator page" );
		setStyle('simulator-page', {'display':'none'});
	}

}

// A specific simulation on the simulation page
function Simulation(surface) {
	// Member functions:

	// Public functions:
	//  - load: initiate a simulation on the server
	//  - pause: halt the simulation
	//  - run: run the simulation
	//  - destroy: destroy/free the objects associated with a simulation
	//  - update: retrieves latest data and updates display
	//  - attach: takes page elements to connect simulation to

	// Private functions:
	//  - _init: called on instantiation
	//  - _draw: callback when data is retrieved - draws results on surface.

	// Private properties:
	//  - _surface: the dojox.gfx surface for drawing and manipulating shapes
	//  - _updater: setInterval property updates display at regular intervals

	// initialise a Simulation object by creating a dojox.gfx surface in a div

	this._init = function() {

		}

	// load a specific simulation
	this.load = function() {
		this.run();
	}

	// attaches the simulation to parts of parent SimPage
	this.attach = function (surface) {
		this._surface = surface;	// attach parent surface
	}

	// initialise instantiation
	this._init();

	// internal function updates display when latest data has been retrieved
	this._draw = function(response, ioArgs) {
		logDebug("updating simulation display");
		this._surface.clear();

		forEach(response.current.balls, function(ball) {
        	        var box = this._surface.createRect({x : ball.x, y : ball.y, width:10, height:10});
        	        box.setFill("red");
		}, this);
	
		var robot = this._surface.createRect({ x : response.current.robot.x, y : response.current.robot.y});
		robot.setFill("black");

	}

	// run (play) the simulation
	this.run = function () {
		this._updater = setInterval( bind(this.update, this),500);
	}

	// pause the simulation
	this.pause = function () {
		clearInterval( this._updater );
	}

	// destroy everything associated with the simulation
	this.destroy = function () {
		this._surface.destroy();
	}

	// get latest data and display
	this.update = function () {
		var data = loadJSONDoc("./sim/getdata", {"teamno":1});
		data.addCallback( bind(this._draw, this) );
		data.addErrback( bind( function(r) {
				this.pause();
				this._prompt = status_button( "Could not load simulation", LEVEL_ERROR,
							      "retry", bind( this.run, this ) )
			}, this )
		);
	}

}
