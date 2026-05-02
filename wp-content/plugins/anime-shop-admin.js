/* anime-shop-admin.js – product image gallery manager (rebuilt) */
(function ($) {
    'use strict';

    /* ───── helpers ───── */
    function syncField() {
        var ids = [];
        $('#abp-gallery-grid .abp-img-card').each(function () {
            var id = $(this).attr('data-id');
            if (id) ids.push(parseInt(id, 10));
        });
        $('#_product_image_ids').val(ids.join(','));

        // style first card as PRIMARY
        $('#abp-gallery-grid .abp-img-card').first()
            .css('border-color', '#2271b1')
            .css('box-shadow', '0 0 0 1px #2271b1');
        $('#abp-gallery-grid .abp-img-card:not(:first-child)')
            .css('border-color', '#dcdcde')
            .css('box-shadow', 'none');
    }

    function addCards(attachments) {
        var grid = $('#abp-gallery-grid');
        // remove empty placeholder
        grid.find('span').remove();

        attachments.each(function (att) {
            var data = att.toJSON();
            if (grid.find('[data-id="' + data.id + '"]').length) return; // dupe

            var src = (data.sizes && data.sizes.medium)
                ? data.sizes.medium.url
                : data.url;

            var card = $('<div class="abp-img-card" data-id="' + data.id + '">' +
                '<img src="' + src + '" />' +
                '<button type="button" class="abp-remove" data-id="' + data.id + '" title="Remove">&times;</button>' +
                '</div>');
            grid.append(card);
        });
        syncField();
    }

    /* ───── media frame ───── */
    var mediaFrame;

    $(document).on('click', '#abp-add-images', function (e) {
        e.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Select Product Images',
            button: { text: 'Add to Gallery' },
            library: { type: 'image' },
            multiple: true
        });

        mediaFrame.on('select', function () {
            var selection = mediaFrame.state().get('selection');
            addCards(selection);
        });

        mediaFrame.open();
    });

    /* ───── remove button ───── */
    $(document).on('click', '.abp-remove', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.abp-img-card').remove();
        syncField();

        if ($('#abp-gallery-grid .abp-img-card').length === 0) {
            $('#abp-gallery-grid').html('<span style="color:#aaa;font-size:13px;">No images yet.</span>');
        }
    });

    /* ───── Quick edit populate ───── */
    if (typeof inlineEditPost !== 'undefined') {
        var _origEdit = inlineEditPost.edit;
        inlineEditPost.edit = function (id) {
            _origEdit.apply(this, arguments);
            var post_id = typeof id === 'object' ? parseInt(this.getId(id)) : parseInt(id);
            if (post_id > 0) {
                setTimeout(function () {
                    var data    = $('#post-' + post_id + ' .anime-quickdata');
                    var editRow = $('#edit-' + post_id);
                    if (!data.length) return;
                    editRow.find('input[name="_price"]').val(data.attr('data-price') || '');
                    editRow.find('input[name="_sale_price"]').val(data.attr('data-sale_price') || '');
                    editRow.find('input[name="_sku"]').val(data.attr('data-sku') || '');
                    editRow.find('input[name="stock"]').val(data.attr('data-stock') || '');
                    editRow.find('select[name="_in_stock"]').val(data.attr('data-in_stock') || '1');
                    var cats = data.attr('data-cats') || '';
                    if (cats) {
                        editRow.find('select[name="anime_quick_categories[]"]').val(cats.split(',').map(function (i) { return i.trim(); }));
                    }
                }, 100);
            }
        };
    }

    /* ───── Copy / Paste (unchanged) ───── */
    $(document).on('click', '#anime-copy-data', function (e) {
        e.preventDefault();
        var obj = {
            title:             $('#title').val() || '',
            _price:            $('input[name="_price"]').val() || '',
            _sale_price:       $('input[name="_sale_price"]').val() || '',
            _sku:              $('input[name="_sku"]').val() || '',
            stock:             $('input[name="stock"]').val() || '',
            _in_stock:         $('select[name="_in_stock"]').val() || '',
            weight:            $('input[name="weight"]').val() || '',
            length:            $('input[name="length"]').val() || '',
            width:             $('input[name="width"]').val() || '',
            height:            $('input[name="height"]').val() || '',
            _product_image_ids: $('#_product_image_ids').val() || ''
        };
        $('#anime-copy-text').val(JSON.stringify(obj)).select();
    });

    $(document).on('click', '#anime-paste-data', function (e) {
        e.preventDefault();
        var raw = $('#anime-copy-text').val();
        if (!raw) { alert('Paste JSON into the textarea first'); return; }
        var obj;
        try { obj = JSON.parse(raw); } catch (err) { alert('Invalid JSON'); return; }

        if (obj.title)       $('#title').val(obj.title);
        if (obj._price)      $('input[name="_price"]').val(obj._price);
        if (obj._sale_price) $('input[name="_sale_price"]').val(obj._sale_price);
        if (obj._sku)        $('input[name="_sku"]').val(obj._sku);
        if (obj.stock)       $('input[name="stock"]').val(obj.stock);
        if (obj._in_stock)   $('select[name="_in_stock"]').val(obj._in_stock);
        if (obj.weight)      $('input[name="weight"]').val(obj.weight);
        if (obj.length)      $('input[name="length"]').val(obj.length);
        if (obj.width)       $('input[name="width"]').val(obj.width);
        if (obj.height)      $('input[name="height"]').val(obj.height);
    });

    /* ───── Variations (unchanged) ───── */
    var renderVariationRow = function (index, obj, attrsDef) {
        attrsDef = attrsDef || [];
        var row  = $('<div class="anime-variation-row"></div>');
        var form = $('<div class="anime-variation-form"></div>');

        attrsDef.forEach(function (a) {
            var sel = $('<select class="anime-var-attr" data-attr="' + a.name + '"></select>');
            sel.append('<option value="">-- ' + a.name + ' --</option>');
            (a.value || []).forEach(function (val) { sel.append('<option value="' + val + '">' + val + '</option>'); });
            form.append($('<label style="display:block;margin-bottom:6px;"></label>').append('<span style="display:inline-block;width:120px;">' + a.name + '</span>').append(sel));
        });

        form.append('<p><label>Price: <input type="text" class="anime-var-price" value="" /></label> <label>Sale: <input type="text" class="anime-var-sale" value="" /></label></p>');
        form.append('<p><label>SKU: <input type="text" class="anime-var-sku" value="" /></label> <label>Stock: <input type="number" class="anime-var-stock" value="0" min="0" style="width:80px;" /></label></p>');
        form.append('<p><a href="#" class="button anime-var-remove">Remove</a></p>');

        var ta = $('<textarea name="anime_variations[]" style="display:none;"></textarea>');
        row.append(form).append(ta);

        if (obj) {
            Object.keys(obj.attrs || {}).forEach(function (k) {
                row.find('select.anime-var-attr').each(function () {
                    var sel = $(this);
                    if (sel.attr('data-attr') === k) sel.val(obj.attrs[k]);
                });
            });
            row.find('.anime-var-price').val(obj.price || '');
            row.find('.anime-var-sale').val(obj.sale_price || '');
            row.find('.anime-var-sku').val(obj.sku || '');
            row.find('.anime-var-stock').val(obj.stock || 0);
        }

        function updateTa() {
            var data = { attrs: {}, price: row.find('.anime-var-price').val(), sale_price: row.find('.anime-var-sale').val(), sku: row.find('.anime-var-sku').val(), stock: parseInt(row.find('.anime-var-stock').val() || 0, 10) || 0 };
            row.find('select.anime-var-attr').each(function () { var a = $(this); if (a.val()) data.attrs[a.attr('data-attr')] = a.val(); });
            ta.val(JSON.stringify(data));
        }
        row.on('change', 'select.anime-var-attr, .anime-var-price, .anime-var-sale, .anime-var-sku, .anime-var-stock', updateTa);
        row.on('click', '.anime-var-remove', function (e) { e.preventDefault(); row.remove(); });
        updateTa();
        return row;
    };

    $(function () {
        var dataEl = $('#anime-shop-data');
        if (!dataEl.length) return;
        var attrs = [], vars = [];
        try { attrs = JSON.parse(dataEl.attr('data-attrs') || '[]'); } catch (e) {}
        try { vars  = JSON.parse(dataEl.attr('data-variations') || '[]'); } catch (e) {}
        var list = $('#anime-variations-list');
        vars.forEach(function (v, i) { list.append(renderVariationRow(i, v, attrs)); });
        $(document).on('click', '#anime-add-variation', function (e) { e.preventDefault(); list.append(renderVariationRow(Date.now(), null, attrs)); });
        $(document).on('click', '#anime-add-attribute', function (e) {
            e.preventDefault();
            var r = $('<div class="anime-attribute-row" style="margin-bottom:8px;"></div>');
            r.append('<input type="text" name="anime_attribute_name[]" placeholder="Attribute name" style="width:25%;margin-right:6px;" />');
            r.append('<input type="text" name="anime_attribute_value[]" placeholder="Comma separated values" style="width:50%;margin-right:6px;" />');
            r.append('<label style="margin-right:6px;"><input type="checkbox" name="anime_attribute_visible[]" value="1" /> Visible</label>');
            r.append(' <a href="#" class="anime-remove-attribute">Remove</a>');
            $('#anime-attributes').append(r);
        });
        $(document).on('click', '.anime-remove-attribute', function (e) { e.preventDefault(); $(this).closest('.anime-attribute-row').remove(); });
    });

})(jQuery);