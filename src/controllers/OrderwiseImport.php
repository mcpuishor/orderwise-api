<?php
namespace Mcpuishor\OrderwiseApi\Controllers;

use Mcpuishor\OrderwiseApi\Extract;

use	Mcpuishor\Greenberrycatalog\Product,
	Mcpuishor\Greenberrycatalog\Category as ProductCategory,
	Mcpuishor\Greenberrycatalog\Variant;

class OrderwiseImport extends \App\Http\Controllers\Controller
{
	use \Mcpuishor\SuperTraits\MeasurableRuntime;

	protected $entities;
	private $updated = 0;
	private $created = 0;

	public function __construct()
	{
		$this->startMeasureRuntime();
	}

    public function postimport()
    {
    	try {
    		$entities = Extract(
    						request()->getContent()
    					)->get();
	    	$entities->each(
						[$this, "save"]
			);
    	} catch (\Exception $e) {
    		return XmlResponse::with(
    			"Error processing: ". $e->getMessage()
    		);
    	}
		return XmlResponse::with(
				"Posted ".$this->entities->count(). " items, "
				."Updated ".$this->updated. " items, "
				."Created ".$this->created. " items. "
				."Processing time ".$this->measureRuntime()
		);	
    }

	public  function save($variantItemArray)
	{
		$variant = Variant::where("owid", "=", $variantItemArray->get("vad_id"))->first();
		$updatedAttributes = [
				"description"   => $this->cannonicalName(
											$variantItemArray->get("vad_abv_description"),
											$variantItemArray->get("vad_description")
										),
				"code"  => $variantItemArray->get("vad_variant_code"),
				"rsp"   =>  $variantItemArray->get("vafp_rsp_inc_vat"),
				"abbreviation"  => $variantItemArray->get("vad_abv_description"),
				"freestock" => $variantItemArray->get("vasq_free_stock_quantity"),
				"special_offer" => $variantItemArray->get("vaa_n_2"),
				"weight"    => $variantItemArray->get("vad_weight"),
				"vat_rate"  => $variantItemArray->get("vr_vat_rate"),
				"vat_code"  => $variantItemArray->get("vr_vat_code"),
				"website"   => $variantItemArray->get("vaa_l_1"),
				"trade"   => $variantItemArray->get("AvailableInTradePoint"),
				"discontinued" => $variantItemArray->get("vas_discontinued_product"),
		];

		if ($variant) {
			$variant->fill($updatedAttributes);

			if ($variant->isDirty()) {
				$variant->save();
				$this->updated++;
			}

			$this->productUpdate($variantItemArray);
		} else {
			//echo "Creating variant ".$updatedAttributes["code"]."\n";
			$variant = Variant::create(
				array_merge(
					[
						"owid" => $variantItemArray->get("vad_id"),
					],
					$updatedAttributes,
					[
						"product_id" 	=> $this->productUpdate($variantItemArray),						
					],
				)
			);
			$this->created++;
		}
	}

	private function productUpdate($variantItemArray)
	{
		$product = Product::where("owid", "=", $variantItemArray->get("pd_id"))->first();

		$updatedAttributes = [
					"description" => $variantItemArray->get("pd_description"),
					"code" => $variantItemArray->get("pd_product_code"),
					"website" => $variantItemArray->get("pa_l_1"),
					"category_id" => $this->categoryUpdate($variantItemArray),
		];

		if ($product) {
			if ($product->category_id != $updatedAttributes["category_id"])
			$product->fill($updatedAttributes);
			if ($product->isDirty()) {

				$product->save();
			}
		} else {
			$product = Product::create(
				array_merge(
					["owid" => $variantItemArray->get("pd_id")],
					$updatedAttributes,
				)
			);
		}
		return $product->id;
	}

	private function categoryUpdate($variantItemArray)
	{
		$category = ProductCategory::where("owid", "=", $variantItemArray->get("main_category_id") ?? 0)->first();

		if ($category) {
			$category->name = $variantItemArray->get("main_category");
			if ($category->isDirty()) {
				$category->save();
			}
		} else {
			$category = ProductCategory::create([
				"owid" => $variantItemArray->get("main_category_id") ?? 0,
				"name" => $variantItemArray->get("main_category"),
			]);
		}
		return $category->id;
	}

	private function cannonicalName($name, $variant) 
	{
		$name = Str::replaceFirst(
						$name." - ", 
						"",
						$variant
					);
		$name = Str::replaceFirst("[H]", "", $name);
		return $name;
	}
}
