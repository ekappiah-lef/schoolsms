<?php

Auth::routes();

//Route::get('/test', 'TestController@index')->name('test');
Route::get('/privacy-policy', 'HomeController@privacy_policy')->name('privacy_policy');
Route::get('/terms-of-use', 'HomeController@terms_of_use')->name('terms_of_use');

/* Fees statement linked from admission email/SMS: no login, signed + expiring URL */
Route::get('fees/statement/{student}', 'FeeStatementController@show')->name('fees.statement')->middleware('signed');

/* MTN MoMo: parent pays from the statement (signed), status polling (by unguessable reference), MTN callback */
Route::post('pay/momo/{student}', 'MomoController@start')->name('momo.pay')->middleware(['signed', 'throttle:10,1']);
Route::get('pay/momo/status/{reference}', 'MomoController@status')->name('momo.status')->middleware('throttle:60,1');
Route::match(['post', 'put'], 'momo/callback', 'MomoController@callback')->name('momo.callback');


Route::group(['middleware' => 'auth'], function () {

    Route::get('/', 'HomeController@dashboard')->name('home');
    Route::get('/home', 'HomeController@dashboard')->name('home');
    Route::get('/dashboard', 'HomeController@dashboard')->name('dashboard');

    /* Switch between the new interface and the classic Blade interface */
    Route::get('/ui/{mode}', 'HomeController@ui_mode')->where('mode', 'new|classic')->name('ui.mode');

    Route::group(['prefix' => 'my_account'], function() {
        Route::get('/', 'MyAccountController@edit_profile')->name('my_account');
        Route::put('/', 'MyAccountController@update_profile')->name('my_account.update');
        Route::put('/change_password', 'MyAccountController@change_pass')->name('my_account.change_pass');
    });

    /*************** Support Team *****************/
    Route::group(['namespace' => 'SupportTeam',], function(){

        /*************** Students *****************/
        Route::group(['prefix' => 'students'], function(){
            Route::get('reset_pass/{st_id}', 'StudentRecordController@reset_pass')->name('st.reset_pass');
            Route::get('graduated', 'StudentRecordController@graduated')->name('students.graduated');
            Route::put('not_graduated/{id}', 'StudentRecordController@not_graduated')->name('st.not_graduated');
            Route::get('list/{class_id}', 'StudentRecordController@listByClass')->name('students.list')->middleware('teamSAT');
            Route::get('search', 'StudentRecordController@search')->name('students.search')->middleware('teamSAT');
            Route::post('notify/{id}', 'StudentRecordController@notify')->name('students.notify')->middleware('teamSA');

            /* Promotions */
            Route::post('promote_selector', 'PromotionController@selector')->name('students.promote_selector');
            Route::get('promotion/manage', 'PromotionController@manage')->name('students.promotion_manage');
            Route::delete('promotion/reset/{pid}', 'PromotionController@reset')->name('students.promotion_reset');
            Route::delete('promotion/reset_all', 'PromotionController@reset_all')->name('students.promotion_reset_all');
            Route::get('promotion/{fc?}/{fs?}/{tc?}/{ts?}', 'PromotionController@promotion')->name('students.promotion');
            Route::post('promote/{fc}/{fs}/{tc}/{ts}', 'PromotionController@promote')->name('students.promote');

        });

        /*************** Users *****************/
        Route::group(['prefix' => 'users'], function(){
            Route::get('reset_pass/{id}', 'UserController@reset_pass')->name('users.reset_pass');
        });

        /*************** TimeTables *****************/
        Route::group(['prefix' => 'timetables'], function(){
            Route::get('/', 'TimeTableController@index')->name('tt.index');

            Route::group(['middleware' => 'teamSA'], function() {
                Route::post('/', 'TimeTableController@store')->name('tt.store');
                Route::put('/{tt}', 'TimeTableController@update')->name('tt.update');
                Route::delete('/{tt}', 'TimeTableController@delete')->name('tt.delete');
            });

            /*************** TimeTable Records *****************/
            Route::group(['prefix' => 'records'], function(){

                Route::group(['middleware' => 'teamSA'], function(){
                    Route::get('manage/{ttr}', 'TimeTableController@manage')->name('ttr.manage');
                    Route::post('/', 'TimeTableController@store_record')->name('ttr.store');
                    Route::get('edit/{ttr}', 'TimeTableController@edit_record')->name('ttr.edit');
                    Route::put('/{ttr}', 'TimeTableController@update_record')->name('ttr.update');
                });

                Route::get('show/{ttr}', 'TimeTableController@show_record')->name('ttr.show');
                Route::get('print/{ttr}', 'TimeTableController@print_record')->name('ttr.print');
                Route::delete('/{ttr}', 'TimeTableController@delete_record')->name('ttr.destroy')->middleware('super_admin');

            });

            /*************** Time Slots *****************/
            Route::group(['prefix' => 'time_slots', 'middleware' => 'teamSA'], function(){
                Route::post('/', 'TimeTableController@store_time_slot')->name('ts.store');
                Route::post('/use/{ttr}', 'TimeTableController@use_time_slot')->name('ts.use');
                Route::get('edit/{ts}', 'TimeTableController@edit_time_slot')->name('ts.edit');
                Route::delete('/{ts}', 'TimeTableController@delete_time_slot')->name('ts.destroy');
                Route::put('/{ts}', 'TimeTableController@update_time_slot')->name('ts.update');
            });

        });

        /*************** Payments *****************/
        Route::group(['prefix' => 'payments'], function(){

            Route::get('manage/{class_id?}', 'PaymentController@manage')->name('payments.manage');
            Route::get('invoice/{id}/{year?}', 'PaymentController@invoice')->name('payments.invoice');
            Route::get('receipts/{id}', 'PaymentController@receipts')->name('payments.receipts');
            Route::get('pdf_receipts/{id}', 'PaymentController@pdf_receipts')->name('payments.pdf_receipts');
            Route::post('select_year', 'PaymentController@select_year')->name('payments.select_year');
            Route::post('select_class', 'PaymentController@select_class')->name('payments.select_class');
            Route::delete('reset_record/{id}', 'PaymentController@reset_record')->name('payments.reset_record');
            Route::post('pay_now/{id}', 'PaymentController@pay_now')->name('payments.pay_now');
            Route::post('invoice/{id}/send', 'PaymentController@sendInvoice')->name('payments.send_invoice');
            Route::post('invoice/{id}/momo', '\App\Http\Controllers\MomoController@staffStart')->name('payments.momo');
            Route::post('manage/{class_id}/send-invoices', 'PaymentController@sendClassInvoices')->name('payments.send_class_invoices');
        });

        /*************** Single payment receipts (view / PDF / send to parent) *****************/
        Route::group(['prefix' => 'receipts', 'where' => ['kind' => 'school|optional']], function(){
            Route::get('{kind}/{id}', 'ReceiptController@show')->name('receipts.show');
            Route::get('{kind}/{id}/pdf', 'ReceiptController@pdf')->name('receipts.pdf');
            Route::post('{kind}/{id}/send', 'ReceiptController@send')->name('receipts.send');
        });

        /*************** Finance: dashboard, income & expenses, service rosters *****************/
        Route::get('finance', 'FinanceDashboardController@index')->name('finance.dashboard');
        Route::get('finance/ledger', 'FinanceLedgerController@index')->name('finance.ledger');
        Route::get('finance/payment-mode', 'FinanceDashboardController@paymentMode')->name('finance.payment_mode');
        Route::get('finance/audit-trail', 'FinanceAuditController@index')->name('finance.audit');
        Route::get('finance/fee-breakdown', 'FeeBreakdownController@index')->name('finance.fee_breakdown');
        Route::get('finance/sales', 'SalesController@index')->name('finance.sales');
        Route::post('finance/sales/items', 'SalesController@storeItem')->name('finance.sales.items.store');
        Route::put('finance/sales/items/{item}', 'SalesController@updateItem')->name('finance.sales.items.update');
        Route::post('finance/sales/items/{item}/restock', 'SalesController@restock')->name('finance.sales.items.restock');
        Route::post('finance/sales/sell', 'SalesController@sell')->name('finance.sales.sell');
        Route::delete('finance/sales/return/{id}', 'SalesController@returnSale')->name('finance.sales.return');
        Route::get('finance/transactions', 'FinanceTransactionController@index')->name('finance.transactions');
        Route::post('finance/transactions', 'FinanceTransactionController@store')->name('finance.transactions.store');
        Route::get('finance/transactions/{id}/edit', 'FinanceTransactionController@edit')->name('finance.transactions.edit');
        Route::put('finance/transactions/{id}', 'FinanceTransactionController@update')->name('finance.transactions.update');
        Route::delete('finance/transactions/{id}', 'FinanceTransactionController@destroy')->name('finance.transactions.destroy');
        Route::get('finance/services/{group}', 'ServiceRosterController@show')->where('group', 'feeding|bus|extracurricular|sales')->name('finance.services');

        /*************** Optional fees (feeding, bus, extra-curricular, books) *****************/
        Route::post('optional_fees/pay/{id}', 'OptionalFeeController@pay')->name('optional_fees.pay');
        Route::delete('optional_fees/reset/{id}', 'OptionalFeeController@reset')->name('optional_fees.reset');

        /*************** Finance configuration *****************/
        Route::group(['prefix' => 'finance'], function(){
            Route::get('config', 'FinanceConfigController@index')->name('finance.config');
            Route::post('options', 'FinanceConfigController@storeOption')->name('finance.options.store');
            Route::put('options/{fee_option}', 'FinanceConfigController@updateOption')->name('finance.options.update');
            Route::delete('options/{fee_option}', 'FinanceConfigController@destroyOption')->name('finance.options.destroy');
            Route::post('routes', 'FinanceConfigController@storeRoute')->name('finance.routes.store');
            Route::put('routes/{bus_route}', 'FinanceConfigController@updateRoute')->name('finance.routes.update');
            Route::delete('routes/{bus_route}', 'FinanceConfigController@destroyRoute')->name('finance.routes.destroy');
            Route::post('notices', 'FinanceConfigController@saveNotices')->name('finance.notices');
            Route::post('discounts', 'FinanceConfigController@storeDiscount')->name('finance.discounts.store');
            Route::put('discounts/{fee_discount}', 'FinanceConfigController@updateDiscount')->name('finance.discounts.update');
            Route::delete('discounts/{fee_discount}', 'FinanceConfigController@destroyDiscount')->name('finance.discounts.destroy');
        });

        /*************** Pins *****************/
        Route::group(['prefix' => 'pins'], function(){
            Route::get('create', 'PinController@create')->name('pins.create');
            Route::get('/', 'PinController@index')->name('pins.index');
            Route::post('/', 'PinController@store')->name('pins.store');
            Route::get('enter/{id}', 'PinController@enter_pin')->name('pins.enter');
            Route::post('verify/{id}', 'PinController@verify')->name('pins.verify');
            Route::delete('/', 'PinController@destroy')->name('pins.destroy');
        });

        /*************** Marks *****************/
        Route::group(['prefix' => 'marks'], function(){

           // FOR teamSA
            Route::group(['middleware' => 'teamSA'], function(){
                Route::get('batch_fix', 'MarkController@batch_fix')->name('marks.batch_fix');
                Route::put('batch_update', 'MarkController@batch_update')->name('marks.batch_update');
                Route::get('tabulation/{exam?}/{class?}/{sec_id?}', 'MarkController@tabulation')->name('marks.tabulation');
                Route::post('tabulation', 'MarkController@tabulation_select')->name('marks.tabulation_select');
                Route::get('tabulation/print/{exam}/{class}/{sec_id}', 'MarkController@print_tabulation')->name('marks.print_tabulation');
            });

            // FOR teamSAT
            Route::group(['middleware' => 'teamSAT'], function(){
                Route::get('/', 'MarkController@index')->name('marks.index');
                Route::get('manage/{exam}/{class}/{section}/{subject}', 'MarkController@manage')->name('marks.manage');
                Route::put('update/{exam}/{class}/{section}/{subject}', 'MarkController@update')->name('marks.update');
                Route::put('comment_update/{exr_id}', 'MarkController@comment_update')->name('marks.comment_update');
                Route::put('skills_update/{skill}/{exr_id}', 'MarkController@skills_update')->name('marks.skills_update');
                Route::post('selector', 'MarkController@selector')->name('marks.selector');
                Route::get('bulk/{class?}/{section?}', 'MarkController@bulk')->name('marks.bulk');
                Route::post('bulk', 'MarkController@bulk_select')->name('marks.bulk_select');
            });

            Route::get('select_year/{id}', 'MarkController@year_selector')->name('marks.year_selector');
            Route::post('select_year/{id}', 'MarkController@year_selected')->name('marks.year_select');
            Route::get('show/{id}/{year}', 'MarkController@show')->name('marks.show');
            Route::get('print/{id}/{exam_id}/{year}', 'MarkController@print_view')->name('marks.print');
            Route::post('email/{id}/{exam_id}/{year}', 'MarkController@email_report')->name('marks.email_report')->middleware('teamSAT');
            Route::post('email-class/{exam}/{class}/{sec_id}', 'MarkController@email_class_reports')->name('marks.email_class_reports')->middleware('teamSAT');

        });

        /*************** Attendance & messages *****************/
        Route::get('attendance', 'AttendanceController@index')->name('attendance.index');
        Route::post('attendance', 'AttendanceController@store')->name('attendance.store');
        Route::post('attendance/alert', 'AttendanceController@alert')->name('attendance.alert');
        Route::get('messages', 'MessageController@index')->name('messages.index');
        Route::get('clearenroll/verify-student', 'ClearEnrollController@student')->name('clearenroll.student');
        Route::get('clearenroll/verify-teacher', 'ClearEnrollController@teacher')->name('clearenroll.teacher');
        Route::post('clearenroll/search', 'ClearEnrollController@search')->name('clearenroll.search');
        Route::get('clearenroll/sync', 'ClearEnrollController@sync')->name('clearenroll.sync');
        Route::post('clearenroll/sync', 'ClearEnrollController@runSync')->name('clearenroll.sync.run');
        Route::get('reports/design', 'ReportDesignController@index')->name('reports.design');
        Route::post('reports/design/{type}', 'ReportDesignController@save')->name('reports.design.save');
        Route::match(['get', 'post'], 'reports/design/{type}/preview', 'ReportDesignController@preview')->name('reports.design.preview');
        Route::get('messages/count', 'MessageController@count')->name('messages.count');
        Route::post('messages', 'MessageController@store')->name('messages.store');
        Route::post('messages/{message}/approve', 'MessageController@approve')->name('messages.approve');
        Route::post('messages/{message}/reject', 'MessageController@reject')->name('messages.reject');

        Route::resource('students', 'StudentRecordController');
        Route::resource('users', 'UserController');
        Route::resource('classes', 'MyClassController');
        Route::resource('sections', 'SectionController');
        Route::resource('subjects', 'SubjectController');
        Route::resource('grades', 'GradeController');
        Route::resource('exams', 'ExamController');
        Route::resource('dorms', 'DormController');
        Route::resource('payments', 'PaymentController');

    });

    /************************ AJAX ****************************/
    Route::group(['prefix' => 'ajax'], function() {
        Route::get('get_lga/{state_id}', 'AjaxController@get_lga')->name('get_lga');
        Route::get('get_class_sections/{class_id}', 'AjaxController@get_class_sections')->name('get_class_sections');
        Route::get('get_class_subjects/{class_id}', 'AjaxController@get_class_subjects')->name('get_class_subjects');
    });

});

/************************ SUPER ADMIN ****************************/
Route::group(['namespace' => 'SuperAdmin','middleware' => 'super_admin', 'prefix' => 'super_admin'], function(){

    Route::get('/settings', 'SettingController@index')->name('settings');
    Route::put('/settings', 'SettingController@update')->name('settings.update');

});

/************************ PARENT ****************************/
Route::group(['namespace' => 'MyParent','middleware' => 'my_parent',], function(){

    Route::get('/my_children', 'MyController@children')->name('my_children');

});
