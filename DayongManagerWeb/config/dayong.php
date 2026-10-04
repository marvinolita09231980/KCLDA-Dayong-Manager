<?php

return [
    // Opening money held before member collections began. Later bank deposits
    // are transfers of recorded collections and must not be counted again.
    'initial_bank_balance' => (float) env('DAYONG_INITIAL_BANK_BALANCE', 2000),
];
