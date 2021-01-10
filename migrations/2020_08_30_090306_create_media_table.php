<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMediaTable extends Migration
{
    public function up()
    {
        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('model');
            $table->unsignedInteger('media_id')->nullable();
            $table->unsignedInteger('collection_id')->nullable();
            $table->string('collection_name')->nullable();
            $table->string('disk');
            $table->string('path');
            $table->text('conversions')->nullable();
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->text('props');
            $table->unsignedInteger('order')->nullable();
            $table->nullableTimestamps();
        });
    }
}
