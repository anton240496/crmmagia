/**
 * CRM License Activation JavaScript
 * Файл: crm-license.js
 */

jQuery(document).ready(function ($) {
    console.log('CRM License JS loaded', crmLicenseConfig);




    $('#activate_domen').on('click', function () {
        console.log('Активация запущена');

        // 1. Получаем данные из полей (только ключ)
        var userEmail = $('#license_mail').val().trim();
        var licenseKey = $('#license_key').val().trim();
        var button = $(this);
        var message = $('#domen_message');

        // 2. Проверяем, что оба поля заполнены
        if (!userEmail) {
            message.html('<div style="color: red;">Введите ваш email</div>');
            $('#license_main').focus();
            return;
        }


        if (!licenseKey) {
            message.html('<div style="color: red;">Введите лицензионный ключ</div>');
            $('#license_key').focus();
            return;
        }

        if (!isValidEmail(userEmail)) {
            message.html('<div style="color: red;">Введите корректный email</div>');
            $('#license_main').focus();
            return;
        }

        button.prop('disabled', true).text('Активируем...');
        message.html('Проверяем данные...');

        // 3. Отправляем AJAX-запрос ТОЛЬКО с ключом
        $.ajax({
            url: crmLicenseConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'crm_activate_license',
                email: userEmail,
                license_key: licenseKey //  ключ
                // security: crmLicenseConfig.nonce
            },
            success: function (response) {
                console.log('AJAX response:', response);

                if (response.success) {
                    message.html('<div style="color: green;">✅ ' + response.data.message + '</div>');

                    // Скрываем все надписи PRO на странице
                    $('.crm_pro').hide();

                    // Перезагружаем страницу через 2 секунды
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    message.html('<div style="color: red;">❌ ' + response.data + '</div>');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', error);
                message.html('<div style="color: red;">❌ Ошибка соединения с сервером</div>');
            },
            complete: function () {
                button.prop('disabled', false).text('Активировать');
            }
        });
    });

    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // 5. Убираем старые обработчики, чтобы не было конфликтов
    $('#activate_license').off('click');
});