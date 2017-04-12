/**
 *
 * Targetpay payment plugin
 *
 */

jQuery().ready(function ($) {
    $('body').on('change', '.sel-payment-data',function () {
        var method = $(this).data('method');
        $('#' + method).prop('checked','checked');
    })
});
