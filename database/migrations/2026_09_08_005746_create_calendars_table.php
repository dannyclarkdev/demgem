<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The world's own calendar: one per campaign, or none.
 *
 * months, weekdays and moons are JSON lists on one row, and that is a recorded
 * exception to "a list gets a child table". They are one configuration: read whole
 * on every page that prints a date, replaced whole on every save, never queried by
 * row, and never gated. Three child tables would cost three joins per page for
 * nothing, and a month is not a thing anybody links to.
 *
 * The current date is three integers rather than a day number, the way every date in
 * this app is. A day number only means something under one shape of the calendar,
 * and the GM edits the shape; "3 Harvestmoon 1042" stays the third day of the third
 * month after the GM gives another month a day.
 *
 * There is no timezone and no real-world anchor. The world's day advances when the
 * GM says it does, and never on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('era', 12)->nullable();
            $table->json('months');
            $table->json('weekdays');
            $table->json('moons');
            $table->unsignedSmallInteger('leap_every')->nullable();
            $table->unsignedTinyInteger('leap_month')->nullable();
            $table->unsignedInteger('current_year');
            $table->unsignedTinyInteger('current_month');
            $table->unsignedSmallInteger('current_day');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
