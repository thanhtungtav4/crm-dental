<?php

use App\Support\DentitionModeResolver;
use Illuminate\Support\Carbon;

it('defaults to adult dentition when birthday is missing', function () {
    $referenceDate = Carbon::parse('2026-02-25');

    $mode = DentitionModeResolver::resolveFromBirthday(null, $referenceDate);

    expect($mode)->toBe(DentitionModeResolver::MODE_ADULT);
});

it('resolves child dentition for patients up to 12 years old', function () {
    $referenceDate = Carbon::parse('2026-02-25');
    $birthday = Carbon::parse('2014-02-26');

    $mode = DentitionModeResolver::resolveFromBirthday($birthday, $referenceDate);

    expect($mode)->toBe(DentitionModeResolver::MODE_CHILD);
});

it('resolves adult dentition for patients older than 12 years old', function () {
    $referenceDate = Carbon::parse('2026-02-25');
    $birthday = Carbon::parse('2013-02-24');

    $mode = DentitionModeResolver::resolveFromBirthday($birthday, $referenceDate);

    expect($mode)->toBe(DentitionModeResolver::MODE_ADULT);
});

it('normalizes unsupported mode values to auto', function () {
    expect(DentitionModeResolver::normalize('mixed'))->toBe(DentitionModeResolver::MODE_AUTO)
        ->and(DentitionModeResolver::normalize(DentitionModeResolver::MODE_CHILD))->toBe(DentitionModeResolver::MODE_CHILD);
});
