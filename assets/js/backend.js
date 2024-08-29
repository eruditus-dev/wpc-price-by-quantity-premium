(function($) {
  'use strict';

  function init_sortable() {
    $('.wpcpq-roles').sortable({
      handle: '.wpcpq-item-move',
    });
  }

  function build_apply_label($item) {
    let apply = $item.find('.wpcpq_apply').val(),
        apply_val = $item.find('.wpcpq_apply_val').val(), apply_label = '';

    if (apply === 'all') {
      apply_label = 'all';
    } else {
      apply_label = apply + ': ' + apply_val;
    }

    $item.find('.wpcpq-label-apply').html(apply_label);
  }

  function init_settings() {
    $('.wpcpq_color_picker').wpColorPicker();
  }

  function init_terms() {
    $('.wpcpq_terms').each(function() {
      var $this = $(this);
      var apply = $this.closest('.wpcpq-item').find('.wpcpq_apply').val();

      if (apply === 'all') {
        $this.closest('.wpcpq-item').find('.hide_if_apply_all').hide();
      } else {
        $this.closest('.wpcpq-item').find('.hide_if_apply_all').show();
      }

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              action: 'wpcpq_search_term',
              nonce: wpcpq_vars.nonce,
              q: params.term,
              taxonomy: apply,
            };
          }, processResults: function(data) {
            var options = [];

            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });

      if ($this.data(apply) !== undefined && $this.data(apply) !== '') {
        $this.val(String($this.data(apply)).split(',')).change();
      } else {
        $this.val([]).change();
      }
    });
  }

  $(document).on('change', '.wpcpq_apply, .wpcpq_apply_val', function() {
    build_apply_label($(this).closest('.wpcpq-item'));
  });

  $(document).on('change', '.wpcpq_apply', function() {
    init_terms();
  });

  $(document).on('click touch', '.wpcpq_overview', function(e) {
    var pid = $(this).attr('data-pid');
    var name = $(this).attr('data-name');
    var type = $(this).attr('data-type');

    if (!$('#wpcpq_overview_popup').length) {
      $('body').append('<div id=\'wpcpq_overview_popup\'></div>');
    }

    $('#wpcpq_overview_popup').html('Loading...');
    $('#wpcpq_overview_popup').dialog({
      minWidth: 460,
      title: '#' + pid + ' - ' + name,
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').bind('click', function() {
          $('#wpcpq_overview_popup').dialog('close');
        });
      },
    });

    var data = {
      action: 'wpcpq_overview', nonce: wpcpq_vars.nonce, pid: pid, type: type,
    };

    $.post(ajaxurl, data, function(response) {
      $('#wpcpq_overview_popup').html(response);
    });

    e.preventDefault();
  });

  $(document).on('change', '.wpcpq_terms', function() {
    var $this = $(this);
    var val = $this.val();
    var apply = $this.closest('.wpcpq-item').find('.wpcpq_apply').val();

    if (Array.isArray(val)) {
      $this.closest('.wpcpq-item').
          find('.wpcpq_apply_val').
          val(val.join()).
          trigger('change');
    } else {
      if (val === null) {
        $this.closest('.wpcpq-item').
            find('.wpcpq_apply_val').
            val('').
            trigger('change');
      } else {
        $this.closest('.wpcpq-item').
            find('.wpcpq_apply_val').
            val(String(val)).
            trigger('change');
      }
    }

    $this.data(apply, $this.val().join());
  });

  $(document).on('click touch', '.wpcpq-item-header', function(e) {
    if (($(e.target).closest('.wpcpq-item-duplicate').length === 0) &&
        ($(e.target).closest('.wpcpq-item-remove').length === 0)) {
      $(this).closest('.wpcpq-item').toggleClass('active');
    }
  }).on('click touch', '.wpcpq-item-remove', function() {
    var r = confirm(
        'Do you want to remove this role? This action cannot undo.');
    if (r == true) {
      $(this).closest('.wpcpq-item').remove();
    }
  }).on('click', '.wpcpq-remove-qty', function() {
    $(this).closest('.input-panel').remove();
  }).on('click', '.wpcpq-add-qty', function() {
    let $this = $(this), index = $this.data('count'), key = $this.data('key'),
        id = $this.data('id'), name = 'wpcpq_prices';

    if (parseInt(id) > 0) {
      // is variation
      name = `wpcpq_prices_v[${id}]`;
    }

    let htmlCode = `<div class="input-panel wpcpq-quantity">
    <span class="wpcpq-qty-wrapper"><input type="number" min="0" step="0.0001" placeholder="quantity" class="wpcpq-quantity-qty" name="${name}[${key}][tiers][${index}][quantity]"></span>
    <span class="wpcpq-price-wrapper hint--top" aria-label="${wpcpq_vars.hint_price}"><input type="text" placeholder="price" class="wpcpq-quantity-price" name="${name}[${key}][tiers][${index}][price]"></span>
    <span class="wpcpq-text-wrapper hint--top" aria-label="${wpcpq_vars.hint_text}"><input type="text" placeholder="after text" class="wpcpq-quantity-text" name="${name}[${key}][tiers][${index}][text]"></span>
    <span class="wpcpq-remove-qty hint--top" aria-label="${wpcpq_vars.hint_remove}">&times;</span>
</div>`;
    $this.before(htmlCode);
    $this.data('count', index + 1);
  }).on('click', '.wpcpq-item-new', function() {
    let $this = $(this), $settings = $this.closest('.wpcpq_settings'),
        id = $this.data('id'), role = $settings.
            find('.wpcpq-item-new-role').
            val();

    $this.prop('disabled', true);
    $settings.find('.wpcpq-items').addClass('wpcpq-items-loading');

    $.post(ajaxurl, {
      action: 'wpcpq_add_role_price',
      nonce: wpcpq_vars.nonce,
      role: role,
      id: id,
    }, function(response) {
      $settings.find('.wpcpq-roles').append(response);
      init_terms();
      init_sortable();
      $this.prop('disabled', false);
      $settings.find('.wpcpq-items').removeClass('wpcpq-items-loading');
    });
  }).on('click', '.wpcpq-item-duplicate', function() {
    let $this = $(this), $settings = $this.closest('.wpcpq_settings'),
        $item = $this.closest('.wpcpq-item'),
        role = $item.find('.wpcpq_role').val(),
        apply = $item.find('.wpcpq_apply').val(),
        apply_val = $item.find('.wpcpq_apply_val').val(),
        method = $item.find('.wpcpq_method').val(),
        layout = $item.find('.wpcpq_layout').val(), tiers = [];

    $settings.find('.wpcpq-items').addClass('wpcpq-items-loading');

    if ($item.find('.wpcpq-quantity').length) {
      $item.find('.wpcpq-quantity').each(function(i) {
        tiers.push({
          quantity: $(this).find('.wpcpq-quantity-qty').val(),
          price: $(this).find('.wpcpq-quantity-price').val(),
          text: $(this).find('.wpcpq-quantity-text').val(),
        });
      });
    }

    $.post(ajaxurl, {
      action: 'wpcpq_add_role_price',
      nonce: wpcpq_vars.nonce,
      role: role,
      apply: apply,
      apply_val: apply_val,
      method: method,
      layout: layout,
      tiers: tiers,
    }, function(response) {
      $(response).insertAfter($item);
      init_terms();
      init_sortable();
      $settings.find('.wpcpq-items').removeClass('wpcpq-items-loading');
    });
  }).on('change', '.wpcpq_enable', function() {
    let state = $(this).val();

    if (state === 'override') {
      $(this).
          closest('.wpcpq_settings').
          find('.wpcpq_settings_override').
          show();
    } else {
      $(this).
          closest('.wpcpq_settings').
          find('.wpcpq_settings_override').
          hide();
    }
  });

  init_terms();
  init_sortable();
  init_settings();
})(jQuery);
