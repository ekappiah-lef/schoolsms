<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One report-card design per class type (Creche, Nursery, KG, Primary, JHS …), stored as settings. */
class CreateReportTemplatesTable extends Migration
{
    public function up()
    {
        Schema::create('report_templates', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('class_type_id')->unique();
            $t->longText('settings');
            $t->unsignedInteger('updated_by')->nullable();
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_templates');
    }
}
