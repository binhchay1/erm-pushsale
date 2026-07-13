<?php

use Illuminate\Support\Facades\Route;

Route::get('pages/1-1-2-lich-su-dang-ky-goi-dich-vu', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'index'])->name('pages.1_1_2');
Route::post('pages/1-1-2-lich-su-dang-ky-goi-dich-vu/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'store'])->name('pages.1_1_2.store');
Route::match(['put', 'patch'], 'pages/1-1-2-lich-su-dang-ky-goi-dich-vu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'update'])->whereNumber('record')->name('pages.1_1_2.update');
Route::delete('pages/1-1-2-lich-su-dang-ky-goi-dich-vu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_1_2.destroy');

Route::get('pages/1-2-1-danh-sach-nhan-vien', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_1Controller::class, 'index'])->name('pages.1_2_1');

Route::get('pages/1-2-2-quan-ly-doi-nhom', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_2Controller::class, 'index'])->name('pages.1_2_2');

Route::get('pages/1-2-3-ca-lam-viec', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'index'])->name('pages.1_2_3');
Route::post('pages/1-2-3-ca-lam-viec/schedule', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'saveSchedule'])->name('pages.1_2_3.schedule');
Route::post('pages/1-2-3-ca-lam-viec/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'store'])->name('pages.1_2_3.store');
Route::match(['put', 'patch'], 'pages/1-2-3-ca-lam-viec/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'update'])->whereNumber('record')->name('pages.1_2_3.update');
Route::delete('pages/1-2-3-ca-lam-viec/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_2_3.destroy');

Route::get('pages/1-2-4-danh-sach-cau-hinh-chia-so', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'index'])->name('pages.1_2_4');
Route::post('pages/1-2-4-danh-sach-cau-hinh-chia-so/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'store'])->name('pages.1_2_4.store');
Route::match(['put', 'patch'], 'pages/1-2-4-danh-sach-cau-hinh-chia-so/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'update'])->whereNumber('record')->name('pages.1_2_4.update');
Route::delete('pages/1-2-4-danh-sach-cau-hinh-chia-so/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_2_4.destroy');

Route::get('pages/1-2-5-cau-hinh-tai-khoan-xem-bao-cao', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'index'])->name('pages.1_2_5');
Route::post('pages/1-2-5-cau-hinh-tai-khoan-xem-bao-cao/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'store'])->name('pages.1_2_5.store');
Route::match(['put', 'patch'], 'pages/1-2-5-cau-hinh-tai-khoan-xem-bao-cao/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'update'])->whereNumber('record')->name('pages.1_2_5.update');
Route::delete('pages/1-2-5-cau-hinh-tai-khoan-xem-bao-cao/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_2_5.destroy');

Route::get('pages/1-2-6-danh-sach-cau-hinh-chia-so-care-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'index'])->name('pages.1_2_6');
Route::post('pages/1-2-6-danh-sach-cau-hinh-chia-so-care-don/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'store'])->name('pages.1_2_6.store');
Route::match(['put', 'patch'], 'pages/1-2-6-danh-sach-cau-hinh-chia-so-care-don/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'update'])->whereNumber('record')->name('pages.1_2_6.update');
Route::delete('pages/1-2-6-danh-sach-cau-hinh-chia-so-care-don/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_2_6.destroy');

Route::get('pages/1-3-1-quan-ly-san-pham', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'index'])->name('pages.1_3_1');
Route::post('pages/1-3-1-quan-ly-san-pham/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'store'])->name('pages.1_3_1.store');
Route::match(['put', 'patch'], 'pages/1-3-1-quan-ly-san-pham/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'update'])->whereNumber('record')->name('pages.1_3_1.update');
Route::delete('pages/1-3-1-quan-ly-san-pham/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_3_1.destroy');
Route::post('pages/1-3-1-quan-ly-san-pham/dialogs/{dialog}/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'storeDialog'])->where('dialog', '[a-z0-9\-]+')->name('pages.1_3_1.dialogs.store');
Route::match(['put', 'patch'], 'pages/1-3-1-quan-ly-san-pham/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'updateDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('pages.1_3_1.dialogs.update');
Route::delete('pages/1-3-1-quan-ly-san-pham/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_1Controller::class, 'destroyDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('pages.1_3_1.dialogs.destroy');

Route::get('pages/1-3-2-danh-sach-combo', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'index'])->name('pages.1_3_2');
Route::post('pages/1-3-2-danh-sach-combo/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'store'])->name('pages.1_3_2.store');
Route::match(['put', 'patch'], 'pages/1-3-2-danh-sach-combo/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'update'])->whereNumber('record')->name('pages.1_3_2.update');
Route::delete('pages/1-3-2-danh-sach-combo/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_3_2.destroy');
Route::post('pages/1-3-2-danh-sach-combo/dialogs/{dialog}/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'storeDialog'])->where('dialog', '[a-z0-9\-]+')->name('pages.1_3_2.dialogs.store');
Route::match(['put', 'patch'], 'pages/1-3-2-danh-sach-combo/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'updateDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('pages.1_3_2.dialogs.update');
Route::delete('pages/1-3-2-danh-sach-combo/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'destroyDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('pages.1_3_2.dialogs.destroy');

Route::get('pages/1-7-1-lich-su-dang-nhap', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_1Controller::class, 'index'])->name('pages.1_7_1');

Route::get('pages/1-7-2-quan-ly-cho-phep-tai-khoan-dang-nhap', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_2Controller::class, 'index'])->name('pages.1_7_2');

Route::get('pages/1-7-3-lich-su-loc-data-chot-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_3Controller::class, 'index'])->name('pages.1_7_3');

Route::get('pages/1-8-1-quan-ly-danh-muc-tac-nghiep', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'index'])->name('pages.1_8_1');
Route::post('pages/1-8-1-quan-ly-danh-muc-tac-nghiep/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'store'])->name('pages.1_8_1.store');
Route::match(['put', 'patch'], 'pages/1-8-1-quan-ly-danh-muc-tac-nghiep/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'update'])->whereNumber('record')->name('pages.1_8_1.update');
Route::delete('pages/1-8-1-quan-ly-danh-muc-tac-nghiep/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_8_1.destroy');

Route::get('pages/1-8-2-thiet-lap-tac-nghiep', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'index'])->name('pages.1_8_2');
Route::post('pages/1-8-2-thiet-lap-tac-nghiep/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'store'])->name('pages.1_8_2.store');
Route::match(['put', 'patch'], 'pages/1-8-2-thiet-lap-tac-nghiep/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'update'])->whereNumber('record')->name('pages.1_8_2.update');
Route::delete('pages/1-8-2-thiet-lap-tac-nghiep/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_8_2.destroy');

Route::get('pages/1-9-thiet-lap-chiet-khau-cod', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'index'])->name('pages.1_9');
Route::post('pages/1-9-thiet-lap-chiet-khau-cod/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'store'])->name('pages.1_9.store');
Route::match(['put', 'patch'], 'pages/1-9-thiet-lap-chiet-khau-cod/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'update'])->whereNumber('record')->name('pages.1_9.update');
Route::delete('pages/1-9-thiet-lap-chiet-khau-cod/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_9.destroy');

Route::get('pages/1-10-import-contact', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_10Controller::class, 'index'])->name('pages.1_10');

Route::get('pages/1-11-cau-hinh-facebook-cua-don-vi', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'index'])->name('pages.1_11');
Route::post('pages/1-11-cau-hinh-facebook-cua-don-vi/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'store'])->name('pages.1_11.store');
Route::match(['put', 'patch'], 'pages/1-11-cau-hinh-facebook-cua-don-vi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'update'])->whereNumber('record')->name('pages.1_11.update');
Route::delete('pages/1-11-cau-hinh-facebook-cua-don-vi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_11.destroy');

Route::get('pages/1-13-1-quan-ly-so-blacklist', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'index'])->name('pages.1_13_1');
Route::post('pages/1-13-1-quan-ly-so-blacklist/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'store'])->name('pages.1_13_1.store');
Route::match(['put', 'patch'], 'pages/1-13-1-quan-ly-so-blacklist/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'update'])->whereNumber('record')->name('pages.1_13_1.update');
Route::delete('pages/1-13-1-quan-ly-so-blacklist/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.1_13_1.destroy');

Route::get('pages/2-3-ho-so-khach-hang', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_3Controller::class, 'index'])->name('pages.2_3');

Route::get('pages/2-4-1-ket-noi-du-lieu', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_1Controller::class, 'index'])->name('pages.2_4_1');
Route::post('pages/2-4-1-ket-noi-du-lieu/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_1Controller::class, 'store'])->name('pages.2_4_1.store');
Route::match(['put', 'patch'], 'pages/2-4-1-ket-noi-du-lieu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_1Controller::class, 'update'])->whereNumber('record')->name('pages.2_4_1.update');
Route::delete('pages/2-4-1-ket-noi-du-lieu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.2_4_1.destroy');

Route::get('pages/2-4-2-ket-noi-du-lieu', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_2Controller::class, 'index'])->name('pages.2_4_2');
Route::post('pages/2-4-2-ket-noi-du-lieu/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_2Controller::class, 'store'])->name('pages.2_4_2.store');
Route::match(['put', 'patch'], 'pages/2-4-2-ket-noi-du-lieu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_2Controller::class, 'update'])->whereNumber('record')->name('pages.2_4_2.update');
Route::delete('pages/2-4-2-ket-noi-du-lieu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_4_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.2_4_2.destroy');

Route::get('pages/2-6-1-import-contact', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_1Controller::class, 'index'])->name('pages.2_6_1');

Route::get('pages/2-6-2-nhap-data-thu-cong', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_2Controller::class, 'index'])->name('pages.2_6_2');

Route::get('pages/2-6-3-ket-noi-cac-don-vi-doi-tac', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'index'])->name('pages.2_6_3');
Route::post('pages/2-6-3-ket-noi-cac-don-vi-doi-tac/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'store'])->name('pages.2_6_3.store');
Route::match(['put', 'patch'], 'pages/2-6-3-ket-noi-cac-don-vi-doi-tac/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'update'])->whereNumber('record')->name('pages.2_6_3.update');
Route::delete('pages/2-6-3-ket-noi-cac-don-vi-doi-tac/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'destroy'])->whereNumber('record')->name('pages.2_6_3.destroy');

Route::get('pages/2-6-4-kho-so-seeding-toi-da-1000', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'index'])->name('pages.2_6_4');
Route::post('pages/2-6-4-kho-so-seeding-toi-da-1000/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'store'])->name('pages.2_6_4.store');
Route::match(['put', 'patch'], 'pages/2-6-4-kho-so-seeding-toi-da-1000/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'update'])->whereNumber('record')->name('pages.2_6_4.update');
Route::delete('pages/2-6-4-kho-so-seeding-toi-da-1000/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'destroy'])->whereNumber('record')->name('pages.2_6_4.destroy');

Route::get('pages/3-1-quan-ly-khach-hang', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_1Controller::class, 'index'])->name('pages.3_1');

Route::get('pages/3-2-quan-ly-chien-dich-cham-soc', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'index'])->name('pages.3_2');
Route::post('pages/3-2-quan-ly-chien-dich-cham-soc/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'store'])->name('pages.3_2.store');
Route::match(['put', 'patch'], 'pages/3-2-quan-ly-chien-dich-cham-soc/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'update'])->whereNumber('record')->name('pages.3_2.update');
Route::delete('pages/3-2-quan-ly-chien-dich-cham-soc/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.3_2.destroy');

Route::get('pages/3-3-1-thong-ke-khach-hang-da-chieu', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_3_1Controller::class, 'index'])->name('pages.3_3_1');

Route::get('pages/3-3-2-thong-ke-khach-hang-chi-tra', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_3_2Controller::class, 'index'])->name('pages.3_3_2');

Route::get('pages/4-2-ho-so-khach-hang', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_2Controller::class, 'index'])->name('pages.4_2');

Route::get('pages/4-3-bang-xep-hang-sales', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_3Controller::class, 'index'])->name('pages.4_3');

Route::get('pages/4-6-1-bao-cao-ti-le-chot-don-theo-tac-nghiep', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_1Controller::class, 'index'])->name('pages.4_6_1');

Route::get('pages/4-6-2-bao-cao-cong-viec-sale', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_2Controller::class, 'index'])->name('pages.4_6_2');

Route::get('pages/4-6-3-bao-cao-nhom-sale', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_3Controller::class, 'index'])->name('pages.4_6_3');

Route::get('pages/4-6-4-bao-cao-data-sale', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_4Controller::class, 'index'])->name('pages.4_6_4');

Route::get('pages/4-6-5-bao-cao-toi-uu-sale', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_5Controller::class, 'index'])->name('pages.4_6_5');

Route::get('pages/5-1-tac-nghiep-van-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_1Controller::class, 'index'])->name('pages.5_1');

Route::get('pages/5-2-1-danh-sach-kho', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'index'])->name('pages.5_2_1');
Route::post('pages/5-2-1-danh-sach-kho/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'store'])->name('pages.5_2_1.store');
Route::match(['put', 'patch'], 'pages/5-2-1-danh-sach-kho/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'update'])->whereNumber('record')->name('pages.5_2_1.update');
Route::delete('pages/5-2-1-danh-sach-kho/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.5_2_1.destroy');
Route::post('pages/5-2-1-danh-sach-kho/dialogs/{dialog}/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'storeDialog'])->where('dialog', '[a-z0-9\-]+')->name('pages.5_2_1.dialogs.store');
Route::match(['put', 'patch'], 'pages/5-2-1-danh-sach-kho/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'updateDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('pages.5_2_1.dialogs.update');
Route::delete('pages/5-2-1-danh-sach-kho/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_1Controller::class, 'destroyDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('pages.5_2_1.dialogs.destroy');

Route::get('pages/5-2-2-danh-sach-san-pham-kho', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_2_2Controller::class, 'index'])->name('pages.5_2_2');

Route::get('pages/5-3-1-phieu-nhap-xuat-kho', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_1Controller::class, 'index'])->name('pages.5_3_1');
Route::post('pages/5-3-1-phieu-nhap-xuat-kho/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_1Controller::class, 'store'])->name('pages.5_3_1.store');
Route::match(['put', 'patch'], 'pages/5-3-1-phieu-nhap-xuat-kho/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_1Controller::class, 'update'])->whereNumber('record')->name('pages.5_3_1.update');
Route::delete('pages/5-3-1-phieu-nhap-xuat-kho/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.5_3_1.destroy');

Route::get('pages/5-3-2-danh-sach-phieu-xuat-nhap-kho', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_2Controller::class, 'index'])->name('pages.5_3_2');

Route::get('pages/5-3-3-lich-su-nhap-xuat-kho-the-kho', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_3Controller::class, 'index'])->name('pages.5_3_3');

Route::get('pages/5-4-danh-sach-bien-ban', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'index'])->name('pages.5_4');
Route::post('pages/5-4-danh-sach-bien-ban/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'store'])->name('pages.5_4.store');
Route::match(['put', 'patch'], 'pages/5-4-danh-sach-bien-ban/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'update'])->whereNumber('record')->name('pages.5_4.update');
Route::delete('pages/5-4-danh-sach-bien-ban/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'destroy'])->whereNumber('record')->name('pages.5_4.destroy');

Route::get('pages/5-5-1-bang-tong-hop-san-pham-nhap-xuat-theo-ngay', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_1Controller::class, 'index'])->name('pages.5_5_1');

Route::get('pages/5-5-2-bang-tong-hop-cho-xuat-theo-ngay', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_2Controller::class, 'index'])->name('pages.5_5_2');

Route::get('pages/5-5-4-bao-cao-tong-hop-phat-sinh-kho', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_4Controller::class, 'index'])->name('pages.5_5_4');

Route::get('pages/5-5-5-bao-cao-care-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_5Controller::class, 'index'])->name('pages.5_5_5');

Route::get('pages/5-5-6-bao-cao-sua-so-dien-thoai-giao-hang', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_6Controller::class, 'index'])->name('pages.5_5_6');

Route::get('pages/5-5-7-tong-hop-trang-thai-giao-hang-theo-van-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_7Controller::class, 'index'])->name('pages.5_5_7');

Route::get('pages/5-5-8-bao-cao-tac-nghiep-care-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_8Controller::class, 'index'])->name('pages.5_5_8');

Route::get('pages/5-8-2-phan-bo-data-care-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'index'])->name('pages.5_8_2');
Route::post('pages/5-8-2-phan-bo-data-care-don/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'store'])->name('pages.5_8_2.store');
Route::match(['put', 'patch'], 'pages/5-8-2-phan-bo-data-care-don/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'update'])->whereNumber('record')->name('pages.5_8_2.update');
Route::delete('pages/5-8-2-phan-bo-data-care-don/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.5_8_2.destroy');

Route::get('pages/6-2-1-quan-ly-chi-phi-don-vi', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'index'])->name('pages.6_2_1');
Route::post('pages/6-2-1-quan-ly-chi-phi-don-vi/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'store'])->name('pages.6_2_1.store');
Route::match(['put', 'patch'], 'pages/6-2-1-quan-ly-chi-phi-don-vi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'update'])->whereNumber('record')->name('pages.6_2_1.update');
Route::delete('pages/6-2-1-quan-ly-chi-phi-don-vi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'destroy'])->whereNumber('record')->name('pages.6_2_1.destroy');

Route::get('pages/6-2-2-danh-muc-chi-phi', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'index'])->name('pages.6_2_2');
Route::post('pages/6-2-2-danh-muc-chi-phi/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'store'])->name('pages.6_2_2.store');
Route::match(['put', 'patch'], 'pages/6-2-2-danh-muc-chi-phi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'update'])->whereNumber('record')->name('pages.6_2_2.update');
Route::delete('pages/6-2-2-danh-muc-chi-phi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'destroy'])->whereNumber('record')->name('pages.6_2_2.destroy');

Route::get('pages/6-2-3-danh-muc-nhom-chi-phi', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'index'])->name('pages.6_2_3');
Route::post('pages/6-2-3-danh-muc-nhom-chi-phi/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'store'])->name('pages.6_2_3.store');
Route::match(['put', 'patch'], 'pages/6-2-3-danh-muc-nhom-chi-phi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'update'])->whereNumber('record')->name('pages.6_2_3.update');
Route::delete('pages/6-2-3-danh-muc-nhom-chi-phi/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'destroy'])->whereNumber('record')->name('pages.6_2_3.destroy');

Route::get('pages/6-2-4-danh-muc-don-vi-tinh', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'index'])->name('pages.6_2_4');
Route::post('pages/6-2-4-danh-muc-don-vi-tinh/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'store'])->name('pages.6_2_4.store');
Route::match(['put', 'patch'], 'pages/6-2-4-danh-muc-don-vi-tinh/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'update'])->whereNumber('record')->name('pages.6_2_4.update');
Route::delete('pages/6-2-4-danh-muc-don-vi-tinh/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'destroy'])->whereNumber('record')->name('pages.6_2_4.destroy');

Route::get('pages/6-3-5-tong-ket-ke-hoach-thang', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'index'])->name('pages.6_3_5');
Route::post('pages/6-3-5-tong-ket-ke-hoach-thang/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'store'])->name('pages.6_3_5.store');
Route::match(['put', 'patch'], 'pages/6-3-5-tong-ket-ke-hoach-thang/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'update'])->whereNumber('record')->name('pages.6_3_5.update');
Route::delete('pages/6-3-5-tong-ket-ke-hoach-thang/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'destroy'])->whereNumber('record')->name('pages.6_3_5.destroy');

Route::get('pages/6-4-danh-sach-xu-ly-xuat-hoa-don-dien-tu', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'index'])->name('pages.6_4');
Route::post('pages/6-4-danh-sach-xu-ly-xuat-hoa-don-dien-tu/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'store'])->name('pages.6_4.store');
Route::match(['put', 'patch'], 'pages/6-4-danh-sach-xu-ly-xuat-hoa-don-dien-tu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'update'])->whereNumber('record')->name('pages.6_4.update');
Route::delete('pages/6-4-danh-sach-xu-ly-xuat-hoa-don-dien-tu/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'destroy'])->whereNumber('record')->name('pages.6_4.destroy');

Route::get('pages/8-5-4-bieu-do-xu-huong', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_4Controller::class, 'index'])->name('pages.8_5_4');

Route::get('pages/8-5-5-bang-tong-hop-ket-qua-chia-data-trong-ngay', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_5Controller::class, 'index'])->name('pages.8_5_5');

Route::get('pages/8-5-9-power-dashboard', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_9Controller::class, 'index'])->name('pages.8_5_9');

Route::get('pages/8-5-10-thong-ke-mua-lai', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_10Controller::class, 'index'])->name('pages.8_5_10');

Route::get('pages/8-5-11-thong-ke-mua-lai-theo-so-san-pham', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_11Controller::class, 'index'])->name('pages.8_5_11');

Route::get('pages/8-5-15-bang-tong-hop-chia-data-trong-ngay-v2', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_15Controller::class, 'index'])->name('pages.8_5_15');

Route::get('pages/8-5-16-bao-cao-care-don', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_16Controller::class, 'index'])->name('pages.8_5_16');

Route::get('pages/8-5-17-bang-tong-hop-chia-so-care-don-trong-ngay', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_17Controller::class, 'index'])->name('pages.8_5_17');

