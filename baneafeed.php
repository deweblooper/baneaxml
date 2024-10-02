<?php  

// Generates XML for Banea on-the-fly with actual data, not as passive XML file, active products only.

$_GET  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);




require_once './../config/config.inc.php';
//Autoload::getInstance()->generateIndex();
$banea_token = Configuration::get('BANEAXML_TOKEN');

if ($_GET['token'] !== $banea_token) {
	die;
}


$context = Context::getContext();
$id_lang = (int)$context->language->id;
$shop_id = (int)$context->shop->id;

// echo XML
$xmlscheme = <<<XML
<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<SHOP>
</SHOP>
XML;
$xml_feed = new SimpleXMLElement($xmlscheme);

$baneaxml_prod_ids = explode(',', Configuration::get('BANEAXML'));
foreach ($baneaxml_prod_ids as $product_id) {
	
	$product = new Product($product_id, false, $id_lang);
	if($product->active != 1){
		continue;
	}
	
	$link = new Link();
	$currency = new Currency((int)Tools::getValue('id_currency'));
	$img = Product::getCover($product_id);
	
	$xml_prod = $xml_feed->addChild('SHOPITEM');
	$xml_prod->addChild('ITEM_ID', $product_id);
	$xml_prod->addChild('PRODUCTNAME', $product->name);
	$xml_prod->addChild('URL', $link->getProductLink($product, $product->link_rewrite, htmlspecialchars(strip_tags($product->category)), $product->ean13, $id_lang, $shop_id, 0, true));
	$xml_prod->addChild('IMGURL', Tools::getShopProtocol() . $link->getImageLink($product->link_rewrite, $product->id.'-'.(int)$img['id_image'], 'large_default'));
		$address = Address::initialize();
		$id_tax_rules = (int)Product::getIdTaxRulesGroupByIdProduct($product_id, $context);
		$tax_manager = TaxManagerFactory::getManager($address, $id_tax_rules);
		$tax_calculator = $tax_manager->getTaxCalculator();
		$price_tax_incl = Tools::ps_round($tax_calculator->addTaxes($product->price), _PS_PRICE_COMPUTE_PRECISION_);
	$xml_prod->addChild('PRICE_VAT', number_format(Tools::convertPrice($price_tax_incl, $currency), 2, '.', ''));
	$xml_prod->addChild('MANUFACTURER', htmlspecialchars(strip_tags(html_entity_decode(Manufacturer::getNameById((int)$product->id_manufacturer), ENT_COMPAT, 'utf-8'))));
		// parent category
		$cat = Db::getInstance()->executeS('
			SELECT cp.id_category, cl.link_rewrite AS link 
			FROM '._DB_PREFIX_.'category_product cp 
			LEFT JOIN '._DB_PREFIX_.'category_lang cl ON (cp.id_category = cl.id_category AND cl.id_lang = '. $id_lang .') 
			WHERE cp.id_product = '. $product_id .' 
			ORDER BY cp.id_category DESC 
			LIMIT 1');
		$cat_id = $cat[0]['id_category'];
		// categories
		$result = Db::getInstance()->ExecuteS('
			SELECT c.id_category, c.id_parent, c.level_depth, cl.name 
			FROM '._DB_PREFIX_.'category c 
			JOIN '._DB_PREFIX_.'category_lang cl ON (cl.id_category = c.id_category AND cl.id_lang = '. $id_lang .') 
			WHERE c.active = 1 AND c.level_depth > 0 
			ORDER BY level_depth, id_category');
		$categories = $categories = array();
		foreach ($result as $row) {
			if ($row['level_depth'] > 2) {
				if(empty($categories[$row['id_parent']])){continue;} 
				$categories[$row['id_category']] = $categories[$row['id_parent']] . " | " . $row['name'];
				$categories[$row['id_category']] = $categories[$row['id_parent']] . " | " . $row['name'];
			} elseif ($row['level_depth'] > 1) {
				$categories[$row['id_category']] = $row['name'];
				$categories[$row['id_category']] = $row['name'];
			}
		}
		unset($result);
		$category_crumb = str_replace('&', '&amp;', $categories[$cat_id]);
	$xml_prod->addChild('CATEGORYTEXT', $category_crumb);
	
}


Header('Content-type: text/xml');
print($xml_feed->asXML());


?>