<?php

Route::group(['prefix' => 'orderwise', 'middleware' => 'api'], function () {
	Route::post('stock', '\Mcpuishor\OrderwiseApi\Controllers\OrderwiseImport@postimport');
	Route::get('stock', '\Mcpuishor\OrderwiseApi\Controllers\OrderwiseImport@doimport');
});