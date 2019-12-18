<?php
namespace Mcpuishor\OrderwiseApi\Controllers;

use Mcpuishor\OrderwiseApi\XmlResponse,
	Illuminate\Support\Facades\Storage,
	Illuminate\Support\Collection as IlluminateCollection,
	Illuminate\Support\Str,
	Illuminate\Http\Request,
	Rodenastyle\StreamParser\StreamParser,
	Mcpuishor\Greenberrycatalog\Product,
	Mcpuishor\Greenberrycatalog\Category as ProductCategory,
	Mcpuishor\Greenberrycatalog\Variant,
	Mcpuishor\XmlUtil\Validator;

class OrderwiseImport extends \App\Http\Controllers\Controller
{
	use \Mcpuishor\SuperTraits\MeasurableRuntime;

	protected $entities;
	private $previous_encoding;
	private $storage;
	private $updated = 0;
	private $created = 0;

	public function __construct()
	{
		$this->startMeasureRuntime();
    	$this->previous_encoding = mb_internal_encoding();
    	mb_internal_encoding('UTF-8');
    	$this->storage = Storage::disk('local');
	}

	private function getFilename()
	{
		return 'temp/stock.xml';
	}

	public function ping() {
		echo "here we are";
		die();
	}

	public function pingget() {
		echo "get where we are";
		die();
	}

	public function processimport()
	{
    	$this->entities= new IlluminateCollection();
    	$file = $this->storage
    				->getAdapter()
    				->applyPathPrefix($this->getFilename());
    	StreamParser::xml($file)
    		->each(function($entity){
				$this->entities->push($entity);
			});
		$this->storage->delete($this->getFilename());
    	// process the payload
    	$this->trimNestedObjects();
    	$this->entities->each(
					[$this, "save"]
		);
	}

	public function doimport()
	{
		if (!$this->storage->exists($this->getFilename())) {
			return OrderwiseXmlResponse::with(
    			"No file to process."
    		);
		}
		if (!Validator::file($this->getFilename())) {
			return OrderwiseXmlResponse::with(
				"Error in processing the import. post is not a valid xml"
			);
		}
		$this->processimport();
		return OrderwiseXmlResponse::with(
				"Posted ".$this->entities->count(). " items, "
				."Updated ".$this->updated. " items, "
				."Created ".$this->created. " items. "
				."Processing time ".$this->measureRuntime()
		);	
	}

    public function postimport()
    {
    	$xml = $this->extractXml(request()->getContent());
    	$this->storage->put($this->getFilename(), $xml);
    	return $this->doimport();
    }

    public function extractXml($content)
    {
		$content = urldecode($content);
		$content = preg_replace('/ExportData\=/', '', $content);
		return $content;
    }

    protected function trimNestedObjects()
	{
		$this->entities = $this->entities
						->map(
							function($entity){
								return $entity->map(function($attribute){
									if (is_object($attribute) && $attribute->isEmpty()) {
										return "";
									}
									return (string) $attribute;
								});
							}
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

	public function __destruct()
	{
    	mb_internal_encoding($this->previous_encoding);
		parent::__destruct();
	}
}
