(function($) {
  'use strict';

  $(function() {
    // ready
    wpcpq_init();
  });

  $(document).on('woosq_loaded', function() {
    // quick view
    if ($('#woosq-popup .wpcpq-table').length) {
      wpcpq_init_table($('#woosq-popup .wpcpq-table'), 'woosq');
    }
  });

  $(document).on('click touch', '.wpcpq-click-yes .wpcpq-item', function(e) {
    let qty = $(this).data('qty');
    let wid = $(this).closest('.wpcpq-wrap').attr('data-id');
    let id = $(this).closest('.wpcpq-table').attr('data-id');
    let $form = $('.wpcpq-id-' + id).closest('form.cart');

    if (!$form.length) {
      $form = $('.wpcpq-id-' + wid).closest('form.cart');
    }

    if ($(this).hasClass('wpcpq-item-default')) {
      qty = 1;
    }

    if ($form.length) {
      $form.find('[name="quantity"]').val(qty).trigger('change');
    }
  });

  $(document).on('click touch', '.wpcpq-item-atc-btn', function(e) {
    let wid = $(this).closest('.wpcpq-wrap').attr('data-id');
    let id = $(this).closest('.wpcpq-table').attr('data-id');
    let qty = $(this).closest('.wpcpq-item').data('qty');
    let $form = $('.wpcpq-id-' + id).closest('form.cart');

    if (!$form.length) {
      $form = $('.wpcpq-id-' + wid).closest('form.cart');
    }

    let $atc = $form.find(
        '.single_add_to_cart_button:not(.wpcpq-item-atc-btn)');

    if ($form.length) {
      $form.find('[name="quantity"]').val(qty).trigger('change');
    }

    if ($atc.length && !$atc.hasClass('disabled')) {
      $atc.trigger('click');
    }
  });

  $(document).on('found_variation', function(e, t) {
    var pid = $(e['target']).closest('.variations_form').data('product_id');
    var $wrap = $(document).find('.wpcpq-wrap-' + pid);

    if (t.wpcpq_enable === 'override' || t.wpcpq_enable === 'global') {
      if (t.wpcpq_table !== undefined) {
        $wrap.replaceWith(wpcpq_decode_entities(t.wpcpq_table));
      } else {
        $wrap.html('');
      }
    } else if (t.wpcpq_enable === 'disable') {
      $wrap.html('');
    } else {
      let variable_table = $('.wpcpq-variable-' + pid).data('wpcpq');

      if (variable_table !== undefined) {
        $wrap.replaceWith(wpcpq_decode_entities(variable_table));
      }
    }

    let $table = $('.wpcpq-table-' + t['variation_id']);

    if ($table.length) {
      if (t['is_purchasable'] && t['is_in_stock']) {
        $table.find('.wpcpq-item-atc-btn').removeClass('disabled');
      } else {
        $table.find('.wpcpq-item-atc-btn').addClass('disabled');
      }

      $table.attr('data-price', t['display_price']);
      wpcpq_init_table($table, 'found_variation');
    }

    $(document.body).trigger('wpcpq_found_variation');
  });

  $(document).on('reset_data', function(e) {
    let pid = $(e['target']).closest('.variations_form').data('product_id');
    let $wrap = $(document).find('.wpcpq-wrap-' + pid);
    let variable_table = $('.wpcpq-variable-' + pid).data('wpcpq');

    if (variable_table !== undefined) {
      $wrap.replaceWith(wpcpq_decode_entities(variable_table));
    }

    let $table = $('.wpcpq-table-' + pid);

    if ($table.length) {
      $table.find('.wpcpq-item-atc-btn').addClass('disabled');
      $table.attr('data-price', $table.attr('data-o_price'));
      wpcpq_init_table($table, 'reset_data');
    }

    $(document.body).trigger('wpcpq_reset_data');
  });

  $(document).on('woovr_selected', function(e, selected) {
    let id = selected.attr('data-id');
    let $table = $('.wpcpq-table-' + id);

    if ($table.length) {
      let purchasable = selected.attr('data-purchasable');

      if (purchasable === 'yes') {
        $table.attr('data-price', selected.attr('data-price'));
      } else {
        $table.attr('data-price', $table.attr('data-o_price'));
      }

      wpcpq_init_table($table, 'woovr_selected');
    }
  });

  $(document).on('change keyup', 'form.cart [name=quantity]', function() {
    let $this = $(this),
        id = $this.closest('form.cart').find('.wpcpq-id').attr('data-id'),
        $table = $('.wpcpq-table-' + id);

    if (!$table.length) {
      $table = $('.wpcpq-wrap-' + id).find('.wpcpq-table');
    }

    if ($table.length) {
      wpcpq_init_table($table, 'quantity');
    }
  });
})(jQuery);

function wpcpq_init() {
  jQuery('.wpcpq-table').each(function() {
    wpcpq_init_table(jQuery(this), 'ready');
  });

  jQuery(document.body).trigger('wpcpq_init');
}

function wpcpq_init_table($table, context) {
  let id = $table.attr('data-id'),
      wid = $table.closest('.wpcpq-wrap').attr('data-id'),
      price = $table.attr('data-price'), method = $table.attr('data-method'),
      step = parseFloat($table.attr('data-step')),
      items = $table.find('.wpcpq-item').get(),
      $main_price = jQuery('.wpcpq-price-' + wid),
      $qty = jQuery('.wpcpq-id-' + id).
          closest('form.cart').
          find('[name=quantity]');

  if (!$qty.length) {
    $qty = jQuery('.wpcpq-id-' + wid).
        closest('form.cart').
        find('[name=quantity]');
  }

  $table.find('.wpcpq-item-default').
      attr('data-price', price).
      find('.wpcpq-item-price-val').
      html(wpcpq_format_price(price));

  // remove all active items first
  $table.find('.wpcpq-item').removeClass('wpcpq-item-active');
  $table.find('.wpcpq-item-total').html('');

  if (!$qty.length) {
    $table.find('.wpcpq-item-default').addClass('wpcpq-item-active');
    return;
  }

  let qty = $qty.val();

  if (qty === '') {
    qty = 1;
  }

  qty = parseFloat(qty);

  // update prices first
  for (let item of items) {
    let $item = jQuery(item), item_price = $item.attr('data-price');

    if (/\%$/gm.test(item_price)) {
      item_price = parseFloat(item_price.replace('%', '')) * price / 100;
    }

    $item.find('.wpcpq-item-price-val').html(wpcpq_format_price(item_price));
  }

  if (method === 'tiered') {
    let total = 0;

    for (let item of items) {
      let $item = jQuery(item), item_price = $item.attr('data-price'),
          item_qty = parseFloat($item.attr('data-qty')),
          next_qty = parseFloat($item.attr('data-next-qty')), item_total = 0;

      if (/\%$/gm.test(item_price)) {
        item_price = parseFloat(item_price.replace('%', '')) * price / 100;
      }

      item_price = parseFloat(item_price).toFixed(wpcpq_vars.price_decimals);

      $item.addClass('wpcpq-item-active');

      if ($item.hasClass('wpcpq-item-default')) {
        if (qty < item_qty) {
          // stop
          total += qty * item_price;
          $item.find('.wpcpq-item-total').
              html('×' + qty + ' (' + wpcpq_format_price(total) + ')');
          break;
        } else {
          // next
          total += (item_qty - step) * item_price;
          $item.find('.wpcpq-item-total').
              html('×' + (item_qty - step) + ' (' + wpcpq_format_price(total) +
                  ')');
        }
      } else {
        if ((next_qty > 0) && (qty >= next_qty > 0)) {
          // next
          item_total = (next_qty - item_qty) * item_price;
          total += item_total;
          $item.find('.wpcpq-item-total').
              html('×' + (next_qty - item_qty) + ' (' +
                  wpcpq_format_price(item_total) + ')');
        } else {
          // stop
          item_total = (qty - item_qty + step) * item_price;
          total += item_total;
          $item.find('.wpcpq-item-total').
              html('×' + (qty - item_qty + step) + ' (' +
                  wpcpq_format_price(item_total) + ')');
          break;
        }
      }
    }

    // summary
    $table.find('.wpcpq-summary-qty').text(qty);
    $table.find('.wpcpq-summary-total').html(wpcpq_format_price(total));
    $table.show();

    // change main price
    if (wpcpq_vars.main_price === 'total') {
      $main_price.html(wpcpq_format_price(total));
    }

    // triggers
    jQuery(document.body).trigger('wpcpq_update_qty', [$table, qty, context]);
    jQuery(document.body).
        trigger('wpcpq_update_total', [$table, total, context]);
    jQuery(document.body).trigger('wpcpq_init_table', [$table, context]);
  } else {
    // reverse items first
    items = items.reverse();

    for (let item of items) {
      let $item = jQuery(item), item_price = $item.attr('data-price'),
          item_qty = parseFloat($item.attr('data-qty'));

      if (/\%$/gm.test(item_price)) {
        item_price = parseFloat(item_price.replace('%', '')) * price / 100;
      }

      item_price = parseFloat(item_price).toFixed(wpcpq_vars.price_decimals);

      if (qty >= item_qty) {
        $item.addClass('wpcpq-item-active');
        price = item_price;
        break;
      }

      if ($item.hasClass('wpcpq-item-default') && (qty < item_qty)) {
        $item.addClass('wpcpq-item-active');
        price = item_price;
        break;
      }
    }

    // summary
    $table.find('.wpcpq-summary-qty').text(qty);
    $table.find('.wpcpq-summary-price').html(wpcpq_format_price(price));
    $table.find('.wpcpq-summary-total').html(wpcpq_format_price(qty * price));
    $table.show();

    // change main price
    if (wpcpq_vars.main_price === 'total') {
      $main_price.html(wpcpq_format_price(qty * price));
    }

    if (wpcpq_vars.main_price === 'price') {
      $main_price.html(wpcpq_format_price(price));
    }

    // triggers
    jQuery(document.body).trigger('wpcpq_update_qty', [$table, qty, context]);
    jQuery(document.body).
        trigger('wpcpq_update_price', [$table, price, context]);
    jQuery(document.body).
        trigger('wpcpq_update_total', [$table, qty * price, context]);
    jQuery(document.body).trigger('wpcpq_init_table', [$table, context]);
  }
}

function wpcpq_format_money(number, places, symbol, thousand, decimal) {
  number = number || 0;
  places = !isNaN(places = Math.abs(places)) ? places : 2;
  symbol = symbol !== undefined ? symbol : '$';
  thousand = thousand !== undefined ? thousand : ',';
  decimal = decimal !== undefined ? decimal : '.';

  let negative = number < 0 ? '-' : '',
      i = parseInt(number = Math.abs(+number || 0).toFixed(places), 10) + '',
      j = 0;

  if (i.length > 3) {
    j = i.length % 3;
  }

  if (wpcpq_vars.trim_zeros === '1') {
    return symbol + negative + (j ? i.substr(0, j) + thousand : '') +
        i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) +
        (places && (parseFloat(number) > parseFloat(i)) ? decimal +
            Math.abs(number - i).
                toFixed(places).
                slice(2).
                replace(/(\d*?[1-9])0+$/g, '$1') : '');
  } else {
    return symbol + negative + (j ? i.substr(0, j) + thousand : '') +
        i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) +
        (places ? decimal + Math.abs(number - i).toFixed(places).slice(2) : '');
  }
}

function wpcpq_format_price(price) {
  let price_html = '<span class="woocommerce-Price-amount amount">';
  let price_formatted = wpcpq_format_money(price, wpcpq_vars.price_decimals, '',
      wpcpq_vars.price_thousand_separator, wpcpq_vars.price_decimal_separator);

  switch (wpcpq_vars.price_format) {
    case '%1$s%2$s':
      //left
      price_html += '<span class="woocommerce-Price-currencySymbol">' +
          wpcpq_vars.currency_symbol + '</span>' + price_formatted;
      break;
    case '%1$s %2$s':
      //left with space
      price_html += '<span class="woocommerce-Price-currencySymbol">' +
          wpcpq_vars.currency_symbol + '</span> ' + price_formatted;
      break;
    case '%2$s%1$s':
      //right
      price_html += price_formatted +
          '<span class="woocommerce-Price-currencySymbol">' +
          wpcpq_vars.currency_symbol + '</span>';
      break;
    case '%2$s %1$s':
      //right with space
      price_html += price_formatted +
          ' <span class="woocommerce-Price-currencySymbol">' +
          wpcpq_vars.currency_symbol + '</span>';
      break;
    default:
      //default
      price_html += '<span class="woocommerce-Price-currencySymbol">' +
          wpcpq_vars.currency_symbol + '</span> ' + price_formatted;
  }

  price_html += '</span>';

  return price_html;
}

function wpcpq_decode_entities(encodedString) {
  let textArea = document.createElement('textarea');
  textArea.innerHTML = encodedString;

  return textArea.value;
}