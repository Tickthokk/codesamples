var sprite_path = '/media/img/sprites/mauve/';

/*
 * Mauve Table
 * 	An extension of the HIVE framework via Hive.Mauve
 * 	@author: Nick Wright <tickthokk@gmail.com>
 *  @description: A table controller that handles filtering, sorting and pagination
 * 	@usage:
 * 		var m = new Hive.Mauve({
 * 			wrap: 'services_rendered',
 * 			url: ajax,
 * 			pagination: false,
 * 			data: {
 * 				action: 'load_services',
 * 				perPage: $$('#services_rendered .per_page')[0].get('value'),
 * 				sort: 'code'
 * 			},
 * 			onStart:function() {
 * 				// Things to do when the table begins 
 * 			},
 * 			onPreFilter:function() {
 * 				// Things to do right before every filter
 * 			},
 * 			onLoad:function() {
 * 				// If there are actions per row (such as edit, or delete), they will be placed here
 * 			}
 * 		});
 * 
 */
Hive.Mauve = new Class({
	
	Implements: [Options, Events],
	
	options:{
		wrap: null, // The main HTML element.  Should contain the table and pagination elements
		url: null, // The AJAX url to call
		autofilter: true, // Filter on keyup events [if true]
		filtering: true,  // Allow filtering events [if true]
		sorting: true,    // Allow sorting events [if true]
		pagination: true, // Allow pagination events [if true]
		auto_refresh: false, // Auto Refresh the table [if true] -- This could be later improved to add a customizable timer.  For right now it'll refresh every 5 minutes.
		auto_start: true, // Automatically filtrate on load
		// The entirety of the AJAX call will be used with these variables.  Custom variables should be passed through the filter, with the assistance of the onPreFilter()
		data:{
			action: '', // The way HIVE's ajax is called, it uses the action variable to relate to the proper function within the AJAX url
			filter: {}, // Any filtration is sent via JSON
			page: 1,	// Default pagination variable.  Always start at page one.
			perPage: 10,// Default pagination variable.  10 seems like a good default.
			sort: '',	// Default column to sort.  As every table is different, there is not value, and should be set when called.
			asc: 'desc'	// Default column sorting direction.  Suggested usage of "asc" and "desc", so you can just use it straight in the MySQL (after proper input cleansing)
		}
	},
	loader:null, // Temporary storage of filtration event.  If still active, it cancels itself so there won't be conflicting outputs
	refresher:null, // Temporary refresher storage event holder.
	
	initialize:function(options) {
		this.setOptions(options);
		this.events(); // Class Events
		this.fireEvent('start'); // Custom Events [onStart]
		if (this.options.auto_start == true)
			this.filtrate();
	},
	
	events:function() {
		var that = this;
		
		if (this.options.filtering == true) {
			// Filtrate Button
			$$('#' + this.options.wrap + ' .filtrate').addEvent('press', this.filtrate.bind(this));
			
			// Reset Filter link
			$$('#' + this.options.wrap + ' .reset_filter').addEvent('click', this.reset_filter.bind(this));
			
			// Pressing "Enter" on any of the inputs and selects
			$$('#' + this.options.wrap + ' thead input[type=text], #' + this.options.wrap + ' thead select').addEvent('keyup', function(key) {
				if (key.code != 13) return;
				if (this.hasClass('date')) return;
				
				that.filtrate();
			});
			
			// Clicking any of the checkboxes
			$$('#' + this.options.wrap + ' thead input[type=checkbox]').addEvent('click', function() {
				that.filtrate();
			});
			
			// On any keyups in the filter fields, auto filter
			if (this.options.autofilter == true) {
				$$('#' + this.options.wrap + ' thead input[type=text]').addEvent('keyup', function() {
					if (this.hasClass('date')) return;
					that.filtrate();
				});
				$$('#' + this.options.wrap + ' thead select').addEvent('change', function() {
					that.filtrate();
				});
			}
		}
		
		if (this.options.sorting == true) {
			// Sorting Headers
			$$('#' + this.options.wrap + ' .heading th').addEvent('click', function() {
				if (this.hasClass('nosort') || this.hasClass('action')) return;
				
				var a = 'asc', d = 'desc';
				
				// If sorting DESC, reverse the "a" and "d" variables
				if (this.get('sort') == 'desc') {
					var x = a;
					a = d;
					d = x;
				}
				
				var div = this.getFirst('div'),
					orderBy = a;
				
				if (this.hasClass('sort'))
					if (div.hasClass(a))
						orderBy = d;
				
				// Start with a clean slate
				$$('#' + that.options.wrap + ' .heading .desc').removeClass('desc');
				$$('#' + that.options.wrap + ' .heading .asc').removeClass('asc')
				$$('#' + that.options.wrap + ' .heading .sort').removeClass('sort');
				
				// Make pretty
				this.addClass('sort');
				div.addClass(orderBy);
				
				// Ammend Data
				that.options.data.sort = this.get('rel');
				that.options.data.asc = orderBy;
				
				// Load
				that.load();
			});
		}
		
		if (this.options.pagination == true) {
			// Per Page changes
			$$('#' + that.options.wrap + ' .per_page').addEvent('change', function() {
				that.options.data.perPage = this.get('value');
				that.options.data.page = 1;
				that.load();
			});
			
			// Page Input changes
			$$('#' + that.options.wrap + ' .page').addEvent('blur', function() {
				var val = parseInt(this.get('value')),
					max = parseInt(this.getNext('.total_pages').get('html'));
				
				var start = that.options.data.page;
				
				if (val < 1) val = 1;
				else if (val > max) val = max;
				
				this.set('value', val);
				
				// If it's the same, don't reload
				if (start == val) return;
				
				that.options.data.page = val;
				that.load();
			});
			
			// Previous and Next buttons
			$$('#' + that.options.wrap + ' .prev, #' + that.options.wrap + ' .next').addEvent('click', function() {
				if (this.hasClass('off')) return;
				
				var par = this.getParent();
				var pageEl = par.getFirst('.page');
				var page = parseInt(pageEl.get('value')),
					max = parseInt(pageEl.getNext('.total_pages').get('html'));
				
				if (this.hasClass('prev')) page--;
				else page++;
				
				pageEl.set('value', page);
				
				that.options.data.page = page;
				that.adjust_page_arrows(); // Maybe remove if Load is doing it anyway?
				that.load();
			});
		}
	},
	
	filtrate:function() {
		this.options.data.filter = {};
		this.options.data.page = 1;
		$(this.options.wrap).getElements('.pager .page').set('value', '1');
		
		// Go through each filter item
		$$('#' + this.options.wrap + ' tr.filter th input, #' + this.options.wrap + ' tr.filter th select').each(function(el) {
			var rel = el.getParent().get('rel'),
				val = el.get('value');
			
			if (el.get('type') == 'checkbox')
				val = el.checked ? 1 : '';
			
			if (val != '')
				this.options.data.filter[rel] = val;
		}.bind(this));
		
		this.fireEvent('preFilter')
		
		this.options.data.filter = JSON.encode(this.options.data.filter);
		
		this.load();
	},
	
	reset_filter:function() {
		this.options.data.filter = {};
		this.options.data.page = 1;
		
		// Clear input fields
		$$('#' + this.options.wrap + ' tr.filter th input, #' + this.options.wrap + ' tr.filter th select').set('value', '');
		
		this.fireEvent('reset');
		
		this.filtrate();
	},
	
	adjust_page_arrows:function() {
		if (this.options.pagination == false) return;
		
		var pagerEl = $$('#' + this.options.wrap + ' .pager')[0];
		
		var prevEl = pagerEl.getFirst('.prev'),
			nextEl = pagerEl.getFirst('.next'),
			max = parseInt(pagerEl.getFirst('.total_pages').get('html'));
		
		if (this.options.data.page <= 1) prevEl.addClass('off').set('src', sprite_path + 'pager_arrow_left_off.gif');
		else prevEl.removeClass('off').set('src', sprite_path + 'pager_arrow_left.gif');
		
		if (this.options.data.page >= max) nextEl.addClass('off').set('src', sprite_path + 'pager_arrow_right_off.gif');
		else nextEl.removeClass('off').set('src', sprite_path + 'pager_arrow_right.gif');
	},
	
	load:function() {
		var object = this;
		
		if (this.loader !== null) this.loader.cancel();
		this.loader = new Hive.Request({
			loaderState: 'Loading Orders',
			url: this.options.url,
			json: true,
			data: this.options.data,
			onSuccess:function(result) {
				$$('#' + this.options.wrap + ' tbody')[0].set('html', result.html);
				$$('#' + this.options.wrap + ' .total_pages').set('html', result.pages);
				$$('#' + this.options.wrap + ' .total_records').set('html', result.records);
				
				this.adjust_page_arrows();
				
				OverText.update();
				
				this.fireEvent('load');
			}.bind(this),
			onComplete:function() {
				this.loader = null;
			}.bind(this)
		}).send();
		
		if (this.options.auto_refresh == true) {
			// Refresh every 5 minutes
			this.refresher = $clear(this.refresher);
			this.refresher = (function() {
				this.filtrate();
			}.bind(this)).delay(1000 * 60 * 5);
		}
	}
});