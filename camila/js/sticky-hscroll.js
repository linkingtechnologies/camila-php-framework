(function ($) {
    var icounter = 0;

    function top(e) {
        return e.offset().top;
    }

    function bottom(e) {
        return e.offset().top + e.height();
    }

    function onscroll(element, scrollbar, scrollLeft) {
        scrollbar.show();
        if (top(element) < top(scrollbar) && bottom(element) > bottom(scrollbar)) {
            scrollbar.find('div').css('width', element.get(0).scrollWidth + 'px');
            scrollbar.css({ left: element.offset().left, width: element.outerWidth() });
            scrollbar.scrollLeft(scrollLeft);
        } else {
            scrollbar.hide();
        }
    }

    function init(container) {
        container.find('.sticky-hscroll').each(function () {
            var element = $(this);
            if (element.data('has-sticky-hscroll') === true) {
                return;
            }
            var id = icounter++;

            element.data('has-sticky-hscroll', true);
            var scrollbar = $('<div class="sticky-hscroll-scrollbar"><div></div></div>');
            var scrollLeft = 0;
            scrollbar.appendTo($(document.body));
            scrollbar.hide();
            scrollbar.css({
                overflowX: 'auto',
                position: 'fixed',
                width: '100%',
                bottom: 0
            });
            scrollbar.find('div').css('height', '1px');
            onscroll(element, scrollbar, scrollLeft);

            // Synchronize scrollbar and element scroll positions
            scrollbar.on('scroll.sticky-hscroll-' + id, function () {
                element.scrollLeft(scrollbar.scrollLeft());
            });
            element.on('scroll.sticky-hscroll-' + id, function () {
                scrollLeft = element.scrollLeft();
            });

            // Adjust scrollbar position on scroll and resize
            $(document).on('scroll.sticky-hscroll-' + id, function () {
                onscroll(element, scrollbar, scrollLeft);
            });
            $(window).on('resize.sticky-hscroll-' + id, function () {
                onscroll(element, scrollbar, scrollLeft);
            });

            // Use MutationObserver to detect when the element is removed from the DOM
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.removedNodes.forEach(function (removed) {
                        if (removed === element[0]) {
                            // Clean up all associated event handlers and elements
                            $(document).off('.sticky-hscroll-' + id);
                            $(window).off('.sticky-hscroll-' + id);
                            scrollbar.off('.sticky-hscroll-' + id);
                            element.off('.sticky-hscroll-' + id);
                            scrollbar.remove();
                            observer.disconnect();
                        }
                    });
                });
            });

            // Start observing the element's parent for child removals
            if (element[0].parentNode) {
                observer.observe(element[0].parentNode, {
                    childList: true
                });
            }
        });
    }

    $.fn.stickyHScroll = function () {
        var container = this;
        init(container);

        // Re-initialize on scroll and resize to catch new elements
        $(document).scroll(function () {
            init(container);
        });
        $(window).resize(function () {
            init(container);
        });
        return this;
    };
}(jQuery));
