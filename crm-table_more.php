<?php
// crm-table_more.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'crm_shabl_kp';

// Получаем ВСЕ данные из БД (фон + логотип)
$row = $wpdb->get_row("SELECT  logo FROM $table_name LIMIT 1");

// Фон
$background_url = '';
if (!empty($row) && !empty($row->background_image)) {
    $background_url = home_url('/' . ltrim($row->background_image, '/'));
}
// else {
//     // Дефолтный логотип
//     $logo_url = plugin_dir_url(__FILE__) . 'assets/img/logo.png';
// }

// Логотип
$logo_url = '';
if (!empty($row) && !empty($row->logo)) {
    $logo_url = home_url('/' . ltrim($row->logo, '/'));
}
?>
<div class="pdf-table" contenteditable="false">
    <table class="textcols table textcols_more">
        <caption class="caption">
            <p class="table_tit two_color">Таблица</p>
            <!-- <img class="img" src="<?php bloginfo('template_url'); ?>/assets/img/Logotype.png" alt=""> -->
            <img src="<?php echo esc_url($logo_url); ?>" alt="Логотип" class="logo_kp">
        </caption>
        <tbody>
            <tr class="tr_name">
                <th class="table_name">
                    <div class="table_cont table_cont_red">№ п/п</div>
                </th>
                <!-- <th class="table_name">
                    <div class="table_cont table_cont_red" 
                    style="border: 1px solid rgb(125, 55, 55); color: #3b2a3a;">№ п/п</div>
                </th> -->
                <th class="table_name">
                    <div class="table_cont table_cont_red">Наименование товара</div>
                </th>
                <th class="table_name">
                    <div class="table_cont table_cont_red">Ед. изм.</div>
                </th>
                <th class="table_name">
                    <div class="table_cont table_cont_red">Кол-во</div>
                </th>
                <th class="table_name">
                    <div class="table_cont table_cont_red">Цена, руб./шт.</div>
                </th>
                <th class="table_name">
                    <div class="table_cont table_cont_red">Сумма, тыс. руб.</div>
                </th>
                <!-- <th class="table_name">
                    <div class="table_cont table_cont_red">Действия</div>
                </th> -->
            </tr>
            <tr class="tr_info black shtit_red">
                <td class="table_info">1</td>
                <td class="table_info naim">
                    <p>Световая вывеска, круглой формы. D - 700 мм.</p>
                    <p>Глубина 80 мм.</p>
                </td>
                <td class="table_info">Шт.</td>
                <td class="table_info">2</td>
                <td class="table_info">11 000</td>
                <td class="table_info">22 000</td>
                <td class="table_info actions-cell">
                    <button class="row-btn add-below-st" type="button" title="Добавить строку Шт ниже">+Шт</button>
                    <button class="row-btn add-below-ysl" type="button"
                        title="Добавить строку Услуга ниже">+Усл</button>
                    <button class="row-btn toggle-type" type="button"
                        title="Сменить тип строки">усл/шт<br>шт/усл</button>
                    <button class="row-btn delete-row" type="button" title="Удалить строку">-1</button>
                </td>
            </tr>
            <tr class="tr_info yellow yslnds_red">
                <td class="table_info">2</td>
                <td class="table_info naim">
                    <p>Световая вывеска, круглой формы. D - 700 мм.</p>
                    <p>Глубина 80 мм.</p>
                </td>
                <td class="table_info">Услуга</td>
                <td class="table_info">2</td>
                <td class="table_info">11 000</td>
                <td class="table_info">22 000</td>
                <td class="table_info actions-cell">
                    <button class="row-btn add-below-st" type="button" title="Добавить строку Шт ниже">+Шт</button>
                    <button class="row-btn add-below-ysl" type="button"
                        title="Добавить строку Услуга ниже">+Усл</button>
                    <button class="row-btn toggle-type" type="button"
                        title="Сменить тип строки">усл/шт<br>шт/усл</button>
                    <button class="row-btn delete-row" type="button" title="Удалить строку">-1</button>
                </td>
            </tr>
            <tr class="tr_info tr_itog black shtit_red">
                <td class="table_info table_itog name" colspan="5">
                    Итого сумма
                </td>
                <td class="table_info table_itog total-sum">44 000</td>
                <!-- <td class="table_info table_itog"></td> -->
            </tr>
            <tr class="tr_info tr_itog yellow yslnds_red">
                <td class="table_info table_itog name" colspan="5" >
                    НДС 22%
                </td>
                <td class="table_info table_itog vat-amount">9 680</td>
                <!-- <td class="table_info table_itog"></td> -->
            </tr>
            <tr class="tr_info tr_itog black shtit_red">
                <td class="table_info table_itog name" colspan="5">
                    Итого с НДС
                </td>
                <td class="table_info table_itog total-with-vat ">
                    <div class="table_all">
                        <span>53 681</span>
                    </div>
                    <div class="table_kupon" style="display: none;">
                        <div class="table_kupon">купон</div>
                        <div>48 312</div>
                    </div>
                </td>
                <!-- <td class="table_info table_itog"></td> -->
            </tr>
        </tbody>
    </table>
</div>
