
(function($) {
	/*
	 * Droppy 0.1.2
	 * (c) 2008 Jason Frame (jason@onehackoranother.com)
	 */
	$.fn.droppy = function(options) {

		options = $.extend({speed: 250}, options || {});

		this.each(function() {

			var root = this, zIndex = 1000;

			function getSubnav(ele) {
				if (ele.nodeName.toLowerCase() == 'li') {
					var subnav = $('> div', ele);
					return subnav.length ? subnav[0] : null;
				} else {
					return ele;
				}
			}

			function getActuator(ele) {
				if (ele.nodeName.toLowerCase() == 'ul') {
					return $(ele).parents('li')[0];
				} else {
					return ele;
				}
			}

			function hide() {
				var subnav = getSubnav(this);
				if (!subnav) return;
				$.data(subnav, 'cancelHide', false);
				if (!$.data(subnav, 'cancelHide')) {
					$(subnav).hide();
				}
			}

			function show() {
				var subnav = getSubnav(this);
				if (!subnav) return;
				$.data(subnav, 'cancelHide', true);
				$(subnav).css({zIndex: zIndex++}).show();
				if (this.nodeName.toLowerCase() == 'ul') {
					var li = getActuator(this);
					$(li).addClass('hover');
					$('> a', li).addClass('hover');
				}
			}

			$('div.lower, li', this).hover(show, hide);
			$('li', this).hover(
				function() { $(this).addClass('hover'); $('> a', this).addClass('hover'); },
				function() { $(this).removeClass('hover'); $('> a', this).removeClass('hover'); }
			);

		});
	};
})(jQuery);

/*
 * qtyAdj - a jQuery plugin for quantity adjustment
 * @author    Insik Kong(alcyone@joymax.com)
 * @date       12.2010
*/
(function($) {
	$.fn.qtyAdj = function() {
		return this.each(function() {
			$(this).after('<span class="ctrl"><span class="add"></span><span class="minus"></span></span>').wrap('<div class="setter"><span class="cont"></span></div>');

			var target = $(this).parents('.qty').find("input");

			$(this).parents('.qty').find('.add').click(function(){
				var currentVal = parseInt(target.val());
				if(isNaN(currentVal)) {
					target.val("1");
				} else if (!isNaN(currentVal)) {
					if(currentVal < 0) {
						target.val(Math.abs(currentVal));
					} else if(currentVal < 99) {
						target.val(currentVal + 1);
					}
				}
				ItemAmountChange();
			});

			$(this).parents('.qty').find('.minus').click(function(){
				var currentVal = parseInt(target.val());
				if (!isNaN(currentVal) && currentVal > 1) {
					target.val(currentVal - 1);
				}
				ItemAmountChange();
			});
		});
	};
})(jQuery);

$(function(){
	$('#lead .search, .intro .tag').pngFix();
//	$('#lead .search input').addClass(function(){
//		if($(this).val() == ''){
//			return getCookie('mlanguage1');
//		}
//	}).focus(function(){
//		$(this).removeClass();
//	}).blur(function(){
//		if($(this).val() == ''){
//			$(this).addClass(function(){			
//				return getCookie('mlanguage1');			
//			});
//		}
//	});

	$('#content .category').droppy({speed: 100});
	// Custom scrollbar disabled - pagination arrows used instead
	// $('#screen .cropped').scrollbar(); // Custom Scrollbar
	$('.qty input').qtyAdj(); // Quantity Adjustment

	// Custom Dropdown Select
	$('.server select').sexyCombo({
		filterFn: function () {
			return true;
		}
	});

	// New & Best
	$('#mycarousel').jcarousel({
		vertical: true,
		animation: 800,
		scroll: 2,
		auto: 3,
		wrap: 'circular',
		buttonNextHTML: '<a href="#mycarousel"></a>',
		buttonPrevHTML: '<a href="#mycarousel"></a>'
	});

	// For Premium Item
	$('.prem .pic, .item-prem .pic').append('<span class="sash"></span>');

	// Item Preview
	$('.list .intro .pic').cluetip({
		cluetipClass: 'spec', 
		topOffset: 15,
		leftOffset: 5,
		local:true,
		hideLocal: true,
		cursor: 'pointer',
		width: '204px',
		showTitle: false,
		clickThrough: true
	});
});