(function ($) {
	"user strict";
	$(window).on("load", () => {
		$("#landing-loader").fadeOut(1000);
		var img = $(".bg__img");
		img.css("background-image", function () {
			var bg = "url(" + $(this).data("img") + ")";
			var bg = `url(${$(this).data("img")})`;
			return bg;
		});
	});
	$(document).ready(function () {
		$(".accordion-title").on("click", function (e) {
			var element = $(this).parent(".accordion-item");
			if (element.hasClass("open")) {
				element.removeClass("open");
				element.find(".accordion-content").removeClass("open");
				element.find(".accordion-content").slideUp(200, "swing");
			} else {
				element.addClass("open");
				element.children(".accordion-content").slideDown(200, "swing");
				element
					.siblings(".accordion-item")
					.children(".accordion-content")
					.slideUp(200, "swing");
				element.siblings(".accordion-item").removeClass("open");
				element
					.siblings(".accordion-item")
					.find(".accordion-title")
					.removeClass("open");
				element
					.siblings(".accordion-item")
					.find(".accordion-content")
					.slideUp(200, "swing");
			}
		});
		$(".nav-toggle").on("click", () => {
			$(".nav-toggle").toggleClass("active");
			$(".menu").toggleClass("active");
		});

		var header = $("header");
		$(window).on("scroll", function () {
			if ($(this).scrollTop() > 160) {
				header.addClass("active");
			} else {
				header.removeClass("active");
			}
		});
		var owl = $(".feature-slider").owlCarousel({
			loop: true,
			margin: 10,
			responsiveClass: true,
			nav: false,
			dots: false,
			loop: false,
			autoplay: true,
			autoplayTimeout: 1500,
			autoplayHoverPause: true,
			responsive: {
				0: {
					items: 1.2,
				},
				500: {
					items: 2,
				},
				768: {
					items: 2.6,
				},
				992: {
					items: 2.7,
					margin: 30,
				},
				1200: {
					items: 4,
					margin: 30,
				},
			},
		});
		var sync1 = $("#sync1");
		var sync2 = $("#sync2");
		var thumbnailItemClass = ".owl-item";
		var slides = sync1
			.owlCarousel({
				startPosition: 12,
				items: 1,
				loop: false,
				margin: 0,
				mouseDrag: false,
				touchDrag: false,
				pullDrag: false,
				scrollPerPage: true,
				autoplayHoverPause: false,
				nav: false,
				dots: false,
				center: true,
			})
			.on("changed.owl.carousel", syncPosition);

		function syncPosition(el) {
			$owl_slider = $(this).data("owl.carousel");
			var loop = $owl_slider.options.loop;

			if (loop) {
				var count = el.item.count - 1;
				var current = Math.round(el.item.index - el.item.count / 2 - 0.5);
				if (current < 0) {
					current = count;
				}
				if (current > count) {
					current = 0;
				}
			} else {
				var current = el.item.index;
			}

			var owl_thumbnail = sync2.data("owl.carousel");
			var itemClass = "." + owl_thumbnail.options.itemClass;

			var thumbnailCurrentItem = sync2
				.find(itemClass)
				.removeClass("synced")
				.eq(current);
			thumbnailCurrentItem.addClass("synced");

			if (!thumbnailCurrentItem.hasClass("active")) {
				var duration = 500;
				sync2.trigger("to.owl.carousel", [current, duration, true]);
			}
		}
		var thumbs = sync2
			.owlCarousel({
				startPosition: 12,
				items: 3,
				loop: false,
				margin: 10,
				autoplay: false,
				nav: false,
				dots: false,
				center: true,
				mouseDrag: false,
				touchDrag: false,
				responsive: {
					500: {
						items: 3,
					},
					768: {
						items: 5,
					},
				},
				onInitialized: function (e) {
					var thumbnailCurrentItem = $(e.target)
						.find(thumbnailItemClass)
						.eq(this._current);
					thumbnailCurrentItem.addClass("synced");
				},
			})
			.on("click", thumbnailItemClass, function (e) {
				e.preventDefault();
				var duration = 500;
				var itemIndex = $(e.target).parents(thumbnailItemClass).index();
				sync1.trigger("to.owl.carousel", [itemIndex, duration, true]);
			})
			.on("changed.owl.carousel", function (el) {
				var number = el.item.index;
				$owl_slider = sync1.data("owl.carousel");
				$owl_slider.to(number, 500, true);
			});
		sync1.owlCarousel();

		sync2.children().each(function (index) {
			$(this).attr("data-position", index);
		});

		$(".owl-item:not(':first-child') .img").on("click", function () {
			sync2.trigger("to.owl.carousel", [$(this).data("position")]);
			$(".img").removeClass("trns");
		});
		$(".owl-item:first-child .img").on("click", function () {
			sync1.trigger("prev.owl.carousel", [0]);
			sync2.trigger("prev.owl.carousel", [0]);
			sync1.trigger("prev.owl.carousel", [0]);
			sync2.trigger("prev.owl.carousel", [0]);
			$(".img").removeClass("trns");
		});

		// Go to the next item
		$(".client-next").on("click", function () {
			sync1.trigger("next.owl.carousel");
			sync2.trigger("next.owl.carousel");
			$(".img").addClass("trns");
		});
		// Go to the previous item
		$(".client-prev").on("click", function () {
			$(".img").addClass("trns");
			sync1.trigger("prev.owl.carousel");
			sync2.trigger("prev.owl.carousel");
		});
		$(".owl-prev").html('<i class="fas fa-angle-left">');
		$(".owl-next").html('<i class="fas fa-angle-right">');

		if ($(".wow").length) {
			var wow = new WOW({
				boxClass: "wow",
				animateClass: "animated",
				offset: 0,
				mobile: true,
				live: true,
			});
			wow.init();
		}
	});
})(jQuery);
