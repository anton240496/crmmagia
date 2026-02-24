<?php

if (!defined('ABSPATH')) {
    exit;
}
setlocale(LC_TIME, 'ru_RU.UTF-8', 'rus_RUS.UTF-8', 'russian');
?>
<table class="textcols_two textcols pdf-table">
    <tr class="textcols-row">
        <td class="textcols-column zakladka zakladka_red">
            <div class="column-content">
                <div class="textcols_header">
                    <div class="texttwo name two_color">
                        <p>Световой короб</p>
                    </div>
                    <div class="podtext name two_color">
                        <p>Подзаголовок</p>
                    </div>
                </div>
                <p class="texnik glav_color">Технические условия:</p>
                <p class="osnova glav_color">
                    Световой короб изготовляется согласно предоставленному эскизу.
                    Лицевая часть – акриловое стекло PLEXISTEK XT 3мм белое. Изображение интерьерная
                    печать 1440 dpi или транслюцентная пленка серии 8500.
                    Боковая часть - ПВХ UNEXT STRONG оклеенная черной пленкой серии 641 №,70. не
                    пропускает свет. Задняя стенка - ПВХ UNEXT STRONG, 6 мм. Подсветка светодиодные модули –
                    премиум SMD 2835 . Открытый блок питания входит в стоимость. Вывеска разборная. Крепеж через
                    заднюю стенку к стене самонарезающимися винтами.
                    Стоимость изготовления и выполнения работ представлены в таблице.
                </p>
            </div>
        </td>
        <td class="textcols-column zakladka zakladka_red">
            <div class="column-content">
                <div class="textcols_header">
                    <div class="texttwo name two_color">
                        <p>Световой короб</p>
                    </div>
                    <div class="podtext name two_color">
                        <p>Подзаголовок</p>
                    </div>
                </div>
                <p class="texnik glav_color">Технические условия:</p>
                <p class="osnova glav_color">
                    Световой короб изготовляется согласно предоставленному эскизу.
                    Лицевая часть – акриловое стекло PLEXISTEK XT 3мм белое. Изображение интерьерная
                    печать 1440 dpi или транслюцентная пленка серии 8500.
                    Боковая часть - ПВХ UNEXT STRONG оклеенная черной пленкой серии 641 №,70. не
                    пропускает свет. Задняя стенка - ПВХ UNEXT STRONG, 6 мм. Подсветка светодиодные модули –
                    премиум SMD 2835 . Открытый блок питания входит в стоимость. Вывеска разборная. Крепеж через
                    заднюю стенку к стене самонарезающимися винтами.
                    Стоимость изготовления и выполнения работ представлены в таблице.
                </p>
            </div>
        </td>
    </tr>
</table>
<?php
$upload_dir = wp_upload_dir();
$target_dir = $upload_dir['basedir'] . '/crm_files/shablon/assets/img/';
$zak_files = glob($target_dir . 'zak.*');
$zak_extension = 'png'; // значение по умолчанию

if (!empty($zak_files)) {
    $zak_file = $zak_files[0];
    $zak_extension = pathinfo($zak_file, PATHINFO_EXTENSION);
}
?>

<style>
    :root {
        --zakladka-image: url('<?php echo esc_url(home_url('/wp-content/plugins/crmmagia/assets/img/zakladka.png')); ?>');
        --zakladka-red: url('<?php echo esc_url(home_url()); ?>/wp-content/uploads/crm_files/shablon/assets/img/zak.<?php echo $zak_extension; ?>');
    }
</style>