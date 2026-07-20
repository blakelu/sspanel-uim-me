<script src="//{$config['jsdelivr_url']}/npm/jquery/dist/jquery.min.js"></script>

<div class="card-inner">
    <h4>
        支付宝当面付
    </h4>
    <p class="card-heading"></p>
    <div id="f2f-qrcode" class="d-flex flex-column align-items-center" aria-live="polite"></div>
    <button class="btn btn-flat waves-attach" id="f2fpay-button" type="button" onclick="f2fpay();">
        生成付款QR Code
    </button>
</div>

<script>
    const f2fPayButton = $('#f2fpay-button');
    const f2fQrcodeContainer = $('#f2f-qrcode');

    function f2fpay() {
        f2fPayButton.prop('disabled', true);
        f2fQrcodeContainer.html(`
            <div class="text-center py-4" role="status">
                <span class="spinner-border text-primary" aria-hidden="true"></span>
                <div class="mt-2">正在生成付款二维码...</div>
            </div>
        `);

        $.ajax({
            type: "POST",
            url: "/user/payment/purchase/f2f",
            dataType: "json",
            data: {
                invoice_id: {$invoice->id},
            },
            success: (data) => {
                if (data.ret === 1) {
                    f2fQrcodeContainer.html('<div class="text-center"><p>手机支付宝扫描支付</p></div>');
                    new QRCode("f2f-qrcode", {
                        text: data.qrcode,
                        width: 200,
                        height: 200,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H,
                    });
                    const redirectButton = $('<button>', {
                        type: 'button',
                        class: 'btn btn-primary mt-3',
                        text: '前往支付',
                    }).on('click', () => {
                        window.location.href = data.qrcode;
                    });
                    f2fQrcodeContainer.append(redirectButton);
                    f2fQrcodeContainer.append(
                        '<div class="text-center my-3"><p>支付成功后请手动刷新页面</p></div>'
                    );
                    f2fPayButton.remove();
                } else {
                    f2fQrcodeContainer.empty();
                    f2fPayButton.prop('disabled', false);
                    $('#fail-message').text(data.msg);
                    $('#fail-dialog').modal('show');
                }
            },
            error: () => {
                f2fQrcodeContainer.empty();
                f2fPayButton.prop('disabled', false);
                $('#fail-message').text('付款二维码生成失败，请稍后重试');
                $('#fail-dialog').modal('show');
            }
        })
    }

    $(() => {
        f2fPayButton.trigger('click');
    });
</script>
