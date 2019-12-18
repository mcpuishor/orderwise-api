<?php

Route::group(['prefix' => 'orderwise'], function () {
	Route::post('stock', '\Mcpuishor\OrderwiseApi\Controllers\OrderwiseImport@postimport');
	Route::get('stock', '\Mcpuishor\OrderwiseApi\Controllers\OrderwiseImport@doimport');
	Route::post('ping', '\Mcpuishor\OrderwiseApi\Controllers\OrderwiseImport@ping');
	Route::get('ping', '\Mcpuishor\OrderwiseApi\Controllers\OrderwiseImport@pingget');
});