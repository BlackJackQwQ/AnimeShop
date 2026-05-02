(function($){
    // Update price and data when variation attributes change
    $(document).on('change', '.anime-attr-select', function() {
        var $product = $(this).closest('.anime-product');
        var variations = $product.data('variations');
        if (!variations || !Array.isArray(variations)) return;

        var selectedAttrs = {};
        $product.find('.anime-attr-select').each(function() {
            var attr = $(this).data('attr');
            var val = $(this).val();
            if (val) selectedAttrs[attr] = val;
        });

        // Find matching variation
        var match = null;
        variations.forEach(function(v) {
            var isMatch = true;
            for (var key in v.attrs) {
                if (String(v.attrs[key]) !== String(selectedAttrs[key])) {
                    isMatch = false;
                    break;
                }
            }
            if (isMatch) match = v;
        });

        var $priceDisplay = $product.find('.current-price');
        if (match) {
            var price = match.sale_price || match.price;
            if (price) {
                // Format as VND
                var priceNum = parseFloat(price);
                var formatted = Math.round(priceNum).toLocaleString('vi-VN') + ' ₫';
                $priceDisplay.text(formatted);
            }
        }
    });

    // Single Product Quantity Selector
    $(document).on('click', '.qty-control', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $input = $btn.siblings('.qty-val');
        var currentQty = parseInt($input.val(), 10) || 1;
        var max = parseInt($input.attr('max'), 10) || 99;
        
        if ($btn.hasClass('qty-plus')) {
            $input.val(Math.min(currentQty + 1, max));
        } else {
            $input.val(Math.max(currentQty - 1, 1));
        }
    });

    // Add to cart AJAX
    $(document).on('click', '.anime-add-to-cart', function(e){
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        var $product = $btn.closest('.anime-product');
        var quantity = parseInt($product.find('.qty-val').val(), 10) || 1;
        
        var attributes = {};
        var selectionIncomplete = false;
        $product.find('.anime-attr-select').each(function() {
            var attr = $(this).data('attr');
            var val = $(this).val();
            if (!val) {
                selectionIncomplete = true;
            }
            attributes[attr] = val;
        });

        if (selectionIncomplete) {
            alert('Please select all options before adding to cart.');
            return;
        }

        $.ajax({
            url: AnimeShop.apiUrl + '/cart/add',
            method: 'POST',
            data: JSON.stringify({
                product_id: id,
                quantity: quantity,
                attributes: attributes
            }),
            contentType: 'application/json; charset=utf-8',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AnimeShop.nonce);
            },
            success: function(resp) {
                if (resp.success) {
                    var originalText = $btn.html();
                    $btn.html('ADDED TO COLLECTION<span style="font-size:18px; margin-left:8px;">✓</span>').css({
                        'background-color': '#28a745',
                        'box-shadow': 'none',
                        'transform': 'none'
                    });
                    setTimeout(function(){
                        $btn.html(originalText).css({
                            'background-color': '',
                            'box-shadow': '',
                            'transform': ''
                        });
                    }, 2000);
                    
                    if (resp.cart_count !== undefined) {
                        var $badge = $('.anime-cart-count');
                        var countVal = parseInt(resp.cart_count) || 0;
                        $badge.text(countVal > 0 ? '(' + countVal + ')' : '');
                    } else {
                        var $count = $('.anime-cart-count');
                        if($count.length) {
                            var currentText = $count.text().replace(/[()]/g, '').trim();
                            var newCount = (parseInt(currentText) || 0) + 1;
                            $count.text('(' + newCount + ')');
                        }
                    }
                } else {
                    var originalTextError = $btn.html();
                    $btn.text('ERROR: ' + (resp.message || 'FAILED')).css('background-color', '#cc0000');
                    setTimeout(function(){
                        $btn.html(originalTextError).css('background-color', '');
                    }, 2500);
                }
            },
            error: function() {
                var originalTextError = $btn.html();
                $btn.text('CONNECTION ERROR').css('background-color', '#cc0000');
                setTimeout(function(){
                    $btn.html(originalTextError).css('background-color', '');
                }, 2500);
            }
        });
    });

    // Standard Cart Interaction: Quantity and Removal
    $(document).on('click', '.cart-qty-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var key = $btn.data('key');
        var $input = $btn.siblings('.cart-qty-val');
        var currentQty = parseInt($input.val(), 10);
        var newQty = $btn.hasClass('plus') ? currentQty + 1 : Math.max(1, currentQty - 1);

        if (newQty === currentQty) return;

        updateCartItem(key, newQty);
    });

    $(document).on('click', '.anime-remove-from-cart', function(e) {
        e.preventDefault();
        var key = $(this).data('key');
        if (confirm('Remove this artifact from your collection?')) {
            updateCartItem(key, 0);
        }
    });

    function updateCartItem(key, qty) {
        var updates = {};
        updates[key] = qty;

        $.ajax({
            url: AnimeShop.apiUrl + '/cart',
            method: 'POST',
            data: JSON.stringify({ updates: updates }),
            contentType: 'application/json; charset=utf-8',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AnimeShop.nonce);
            },
            success: function(resp) {
                if (resp.success) {
                    location.reload(); // Simple refresh to update totals
                } else {
                    alert('Error updating cart');
                }
            },
            error: function() {
                alert('Connection error while updating cart');
            }
        });
    }

    // Legacy Cart Update Support
    $('#anime-cart-update').on('click', function(e){
        e.preventDefault();
        var data = $('#anime-cart-form').serializeArray();
        var updates = {};
        $.each(data, function(i,v){
            var m = v.name.match(/updates\[(.+)\]/); 
            if(m) updates[m[1]] = parseInt(v.value,10);
        });
        updateCartItemMap(updates);
    });

    function updateCartItemMap(updates) {
        $.ajax({
            url: AnimeShop.apiUrl + '/cart',
            method: 'POST',
            data: JSON.stringify({updates: updates}),
            contentType: 'application/json; charset=utf-8',
            beforeSend:function(xhr){
                xhr.setRequestHeader('X-WP-Nonce', AnimeShop.nonce);
            },
            success:function(){
                location.reload();
            }
        });
    }

    // Checkout form submission
    $(document).on('submit', '#anime-checkout-form', function(e){
        e.preventDefault();
        var data = {};
        $.each($(this).serializeArray(), function(i,v){ data[v.name] = v.value; });
        $.ajax({
            url: AnimeShop.apiUrl + '/checkout',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json; charset=utf-8',
            beforeSend:function(xhr){
                xhr.setRequestHeader('X-WP-Nonce', AnimeShop.nonce);
            },
            success:function(resp){
                if(resp && resp.success){
                    if (resp.redirect) {
                        window.location.href = resp.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Acquisition Error: ' + (resp.message || 'Verification failed.'));
                }
            },
            error:function(){
                alert('Error placing order');
            }
        });
    });

    // Payment method toggle
    $(document).on('change', 'input[name="payment_method"]', function() {
        var val = $(this).val();
        $('.payment-method-card').removeClass('active');
        $(this).closest('.payment-method-card').addClass('active');
        if (val === 'card') {
            $('#anime-card-fields').slideDown();
        } else {
            $('#anime-card-fields').slideUp();
        }
    });


    // Login AJAX
    $(document).on('submit', '#anime-login-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg = $('#anime-login-msg');
        var data = $form.serialize() + '&action=anime_login';

        $msg.hide().removeClass('error success').text('');
        $form.find('button').prop('disabled', true).text('Logging in...');

        $.post(AnimeShop.ajaxUrl, data, function(resp) {
            if (resp.success) {
                $msg.addClass('success').text('Login successful! Redirecting...').show();
                setTimeout(function() {
                    window.location.href = resp.data.redirect || AnimeShop.homeUrl;
                }, 1000);
            } else {
                $msg.addClass('error').text(resp.data.message || 'Login failed.').show();
                $form.find('button').prop('disabled', false).text('Log In');
            }
        });
    });

    // Register AJAX
    $(document).on('submit', '#anime-register-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg = $('#anime-register-msg');
        var data = $form.serialize() + '&action=anime_register';

        $msg.hide().removeClass('error success').text('');
        $form.find('button').prop('disabled', true).text('Creating account...');

        $.post(AnimeShop.ajaxUrl, data, function(resp) {
            if (resp.success) {
                $msg.addClass('success').text('Account created! Welcome to the shop.').show();
                setTimeout(function() {
                    window.location.href = resp.data.redirect || AnimeShop.homeUrl;
                }, 1500);
            } else {
                $msg.addClass('error').text(resp.data.message || 'Registration failed.').show();
                $form.find('button').prop('disabled', false).text('Create Account');
            }
        });
    });
    // Boutique Essential Discovery
    var discoveryTimeout = null;
    var currentRequest = null; // To handle rapid clicking / race conditions

    function getDiscoveryState() {
        var cats = [];
        $('.cat-filter:checked').each(function() { cats.push($(this).val()); });

        var attrs = {};
        $('.attr-filter:checked').each(function() {
            var slug = $(this).data('slug');
            var val = $(this).val();
            if (!attrs[slug]) attrs[slug] = [];
            attrs[slug].push(val);
        });

        return {
            q: $('#discovery-search').val() || '',
            cats: cats,
            attrs: attrs,
            min: $('.min-price').val() || 0,
            max: $('.max-price').val() || 0,
            sort: $('#discovery-sort').val() || 'latest',
            paged: 1
        };
    }

    function runDiscovery(paged) {
        var state = getDiscoveryState();
        if (paged) state.paged = paged;

        // Abort previous request if still in flight
        if (currentRequest) {
            currentRequest.abort();
        }

        var $grid = $('#discovery-grid');
        $grid.css('opacity', '0.5');

        currentRequest = $.ajax({
            url: AnimeShop.apiUrl + '/discovery',
            method: 'GET',
            data: state,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AnimeShop.nonce);
            },
            success: function(resp) {
                currentRequest = null;
                if (resp.success) {
                    $grid.html(resp.html).css('opacity', '1');
                    $('#discovery-count').text('Found ' + resp.count + ' artifacts');
                    $('#discovery-pagination').html(resp.pagination);
                    updateActivePills(state);
                    
                    var url = new URL(window.location.href);
                    url.searchParams.delete('cat[]');
                    url.searchParams.delete('q');
                    state.cats.forEach(function(c){ url.searchParams.append('cat[]', c); });
                    if (state.q) url.searchParams.set('q', state.q);
                    url.searchParams.set('sort', state.sort);
                    window.history.pushState({}, '', url);
                }
            }
        });
    }

    function updateActivePills(state) {
        var $container = $('#active-pills');
        $container.empty();

        state.cats.forEach(function(c) {
            $container.append('<div class="active-pill" data-type="cat" data-val="'+c+'">'+c+' <span class="pill-remove">×</span></div>');
        });

        for (var slug in state.attrs) {
            state.attrs[slug].forEach(function(v) {
                $container.append('<div class="active-pill" data-type="attr" data-slug="'+slug+'" data-val="'+v+'">'+v+' <span class="pill-remove">×</span></div>');
            });
        }
        
        if ($('.active-pill').length > 0) {
            $container.append('<div class="active-pill clear-all-pills" style="cursor:pointer; background:#000; color:#fff; border-color:#000;">Clear All</div>');
        }
    }

    $(document).on('change', '.cat-filter, .attr-filter, #discovery-sort', function() {
        runDiscovery(1);
    });

    $(document).on('input', '.min-price, .max-price, #discovery-search', function() {
        clearTimeout(discoveryTimeout);
        discoveryTimeout = setTimeout(function() {
            runDiscovery(1);
        }, 500);
    });

    $(document).on('click', '.active-pill:not(.clear-all-pills)', function() {
        var type = $(this).data('type');
        var val = $(this).data('val');
        var slug = $(this).data('slug');
        if (type === 'cat') $('.cat-filter[value="'+val+'"]').prop('checked', false);
        else $('.attr-filter[data-slug="'+slug+'"][value="'+val+'"]').prop('checked', false);
        runDiscovery(1);
    });

    $(document).on('click', '.clear-all-pills, .boutique-reset', function(e) {
        e.preventDefault();
        $('.cat-filter, .attr-filter').prop('checked', false);
        $('.min-price, .max-price').val('');
        $('#discovery-search').val('');
        $('#discovery-sort').val('latest');
        runDiscovery(1);
    });

    $(document).on('click', '#discovery-pagination a', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (!href) return;
        var url = new URL(href, window.location.origin);
        var paged = url.searchParams.get('paged') || 1;
        runDiscovery(paged);
        $('html, body').animate({ scrollTop: $('#anime-shop-discovery').offset().top - 100 }, 300);
        $('html, body').animate({ scrollTop: $('#anime-shop-discovery').offset().top - 100 }, 300);
    });

    // --- Premium Single Enhancements ---

    // Accordions
    $(document).on('click', '.anime-accordion-header', function() {
        var $item = $(this).closest('.anime-accordion-item');
        var $content = $item.find('.anime-accordion-content');
        var $icon = $(this).find('.acc-icon');
        
        if ($item.hasClass('active')) {
            $content.slideUp(250);
            $item.removeClass('active');
            $icon.text('+');
        } else {
            $content.slideDown(250);
            $item.addClass('active');
            $icon.text('-');
        }
    });

    // Lightbox
    $(document).on('click', '.anime-lightbox-trigger', function() {
        var src = $(this).data('full');
        if (src) {
            $('#lightbox-img').attr('src', src);
            $('#anime-lightbox').fadeIn(300).css('display', 'flex');
            $('body').css('overflow', 'hidden');
        }
    });

    $(document).on('click', '.lightbox-close, #anime-lightbox', function(e) {
        if ($(e.target).closest('#lightbox-img').length) {
            // Clicked image itself, close it too for convenience
            $('#anime-lightbox').fadeOut(250);
            $('body').css('overflow', '');
            return;
        }
        $('#anime-lightbox').fadeOut(250);
        $('body').css('overflow', '');
    });

    // Search Overlay
    $(document).on('click', '#anime-search-trigger', function() {
        $('#anime-search-overlay').fadeIn(300).addClass('active');
        $('#anime-search-overlay input').focus();
    });

    $(document).on('click', '#anime-search-close', function() {
        $('#anime-search-overlay').fadeOut(300).removeClass('active');
    });

    // Mobile Toggle
    $(document).on('click', '#anime-mobile-toggle', function() {
        $(this).toggleClass('active');
        $('#anime-mobile-nav').toggleClass('active');
        if ($(this).hasClass('active')) {
            $('body').css('overflow', 'hidden');
        } else {
            $('body').css('overflow', 'auto');
        }
    });

    // Close mobile nav on link click
    $(document).on('click', '.mobile-links a', function() {
        $('#anime-mobile-toggle').removeClass('active');
        $('#anime-mobile-nav').removeClass('active');
        $('body').css('overflow', 'auto');
    });

    $(document).on('click', '.dashboard-tabs a', function(e) {
        e.preventDefault();
        var $link = $(this);
        var target = $link.attr('href').replace('#', '');
        
        $('.dashboard-tabs a').removeClass('active');
        $link.addClass('active');
        
        $('.dashboard-tab-content').removeClass('active-tab');
        $('#' + target + '-tab').addClass('active-tab');
    });

    if ($('.anime-clean-dashboard').length > 0) {
        var hash = window.location.hash;
        if (hash) {
            $('.dashboard-tabs a[href="' + hash + '"]').click();
        }
    }

    $(document).on('submit', '#anime-settings-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $(document.activeElement).is('button[type="submit"]') ? $(document.activeElement) : $form.find('button[type="submit"]');
        var $resp = $btn.siblings('.dashboard-msg');
        var data = {};
        
        var formData = $form.serializeArray();
        $.each(formData, function(i, field) {
            data[field.name] = field.value;
        });
        
        data.theme_dark = $form.find('input[name="theme_dark"]').is(':checked') ? 1 : 0;

        var originalText = $btn.text();
        $btn.text('Saving...').prop('disabled', true);
        $resp.hide().removeClass('success-msg error-msg');

        $.ajax({
            url: AnimeShop.apiUrl + '/update-settings',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json; charset=utf-8',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AnimeShop.nonce);
            },
            success: function(response) {
                $btn.text(originalText).prop('disabled', false);
                if (response.success) {
                    $resp.text(response.message || 'Updated.').addClass('success-msg').fadeIn();
                    setTimeout(function() { $resp.fadeOut(); }, 3000);
                    if (data.theme_dark == 1) {
                        $('body').addClass('dark-theme');
                    } else {
                        $('body').removeClass('dark-theme');
                    }
                } else {
                    $resp.text(response.message || 'Error.').addClass('error-msg').fadeIn();
                }
            },
            error: function(xhr) {
                $btn.text(originalText).prop('disabled', false);
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Error';
                $resp.text(err).addClass('error-msg').fadeIn();
            }
        });
    });

})(jQuery);