<?php
if (!defined('_PS_VERSION_'))
  exit;
 
class BaneaXML extends Module
{
	protected $exportDir = '';
	
  public function __construct()
  {
    $this->name = 'baneaxml';
    $this->tab = 'advertising_marketing';
    $this->version = '2.1.1';
    $this->author = 'waterwhite';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 
    $this->bootstrap = true;
	 $this->exportDir = rtrim(_PS_ROOT_DIR_, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR;
 
    parent::__construct();
 
    $this->displayName = $this->l('Banea XML');
    $this->description = $this->l('This module generate XML feed for DogNet Banea Ads.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 
    if (!Configuration::get('BANEAXML'))      
      $this->warning = $this->l('No value inserted.');
  }
	
	
	// install and register hooks
	public function install()
	{
		if (Shop::isFeatureActive())
    Shop::setContext(Shop::CONTEXT_ALL);

		if (!file_exists($this->exportDir)) {
			 mkdir($this->exportDir, 0775);
		}

		if (!parent::install() ||
			!Configuration::updateValue('BANEAXML_TOKEN', substr(md5(time('now')), -15, 7))  ||
			!Configuration::updateValue('BANEAXML_FOLDER', 'xml') ||
			!Configuration::updateValue('BANEAXML', '1,3,5,8') ||	// saving first value to ps config table
			!@copy(_PS_MODULE_DIR_.$this->name.DIRECTORY_SEPARATOR.'baneafeed.php', $this->exportDir.'baneafeed.php')
			)
			return false;
		
		return true;
	}
	
	
	// uninstall
	public function uninstall()
	{
		unlink( Tools::getShopDomainSsl(true, true).__PS_BASE_URI__ . Configuration::get('BANEAXML_FOLDER').'/banea_'. Configuration::get('BANEAXML_TOKEN') .'.xml');
		if (!parent::uninstall() ||
			!Configuration::deleteByName('BANEAXML_TOKEN') ||
			!Configuration::deleteByName('BANEAXML_FOLDER') ||
			!Configuration::deleteByName('BANEAXML') ||
			(file_exists($this->exportDir.'baneafeed.php') && !@unlink($this->exportDir.'baneafeed.php'))
			)
			return false;
		
		return true;
	}
	
	
	// generate products and id´s list with multiselected values
	public function listProducts() {
		global $cookie;
		$baneaxml_prod_ids = explode(',', Configuration::get('BANEAXML'));
		$this->_html = '
		<div class="form-wrapper">';
			$products = Db::getInstance()->ExecuteS('
				SELECT p.id_product, p.reference, pl.name
				FROM '._DB_PREFIX_.'product p
				LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = '. intval($cookie->id_lang) .')
				WHERE p.active = 1
				GROUP BY p.id_product
				ORDER BY p.id_product
				');

			$this->_html .= '
				<select id="baneaxml_prod_ids" name="baneaxml_prod_ids[]" style="width:100%;height:300px;padding:7px;" multiple="multiple">';
					foreach ($products as $product) {
						$this->_html .= '<option value="'. $product['id_product'] .'"'. (in_array($product['id_product'], $baneaxml_prod_ids)?' selected':'') .'>['. $product['id_product'] .'] &nbsp; '. $product['name'] .'</option>';
					}
				$this->_html .= '</select>
			</div>';
			return $this->_html;
	}
	
	
	// helps to generate setting form
	public function displayForm()
	{
    // Get default language
    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		
    // Init Fields form array
    $fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Choose from product list'),
			),
			'input' => array(
				array(
					'type' => 'free',
					'label' => $this->l('Products:'),
					'desc' => $this->l('Hold CTRL key and mouse click in order to select or deselect more products, otherwise you will select just one line. To not change selection You can get back to main Modules page without saving last settings.'),
					'name' => 'baneaxml_prod_list'
				),
				array(
					'type' => 'free',
					'name' => 'custom_js'
				),
				array(
					'type' => 'free',
					'desc' => $this->l('This is a link to your XML file. It can be used as external feed.'),
					'name' => 'url_feed'
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'btn btn-default pull-right'
			)
    );
		
    $helper = new HelperForm();
		
    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		
    // Language
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;
		
    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit'.$this->name;
    $helper->toolbar_btn = array(
		'save' =>
		array(
		'desc' => $this->l('Save'),
		'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
		'&token='.Tools::getAdminTokenLite('AdminModules'),
		),
		'back' => array(
		'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
		'desc' => $this->l('Back to list')
		)
    );
		
    // Load current values
		$helper->fields_value = array(
			'baneaxml_prod_list' => $this->listProducts(),
			'custom_js' => '
			<script type="text/javascript">
				// passive first count
				$(document).ready(function() {
					var count = $("#baneaxml_prod_ids option:selected").length;
					$("label.control-label").append("("+count+")");
				});
				// dynamic counting on click
				$(document).click(function() {
					var count = $("#baneaxml_prod_ids option:selected").length;
					$("label.control-label").text("'.$this->l('Products:').' ("+count+")");
				});
			</script>
			',
			'url_feed' => '<strong>'.$this->l('XML feed file URL').': </strong><a href="'.Tools::getShopDomainSsl(true, true).__PS_BASE_URI__ . Configuration::get('BANEAXML_FOLDER').'/baneafeed.php?token='. Configuration::get('BANEAXML_TOKEN') .'" target="_blank">'.Tools::getShopDomainSsl(true, true).__PS_BASE_URI__ . Configuration::get('BANEAXML_FOLDER').'/baneafeed.php?token='. Configuration::get('BANEAXML_TOKEN') .'</a>'
		);
		
    return $helper->generateForm($fields_form);
	}
	
/*
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function makeXML() {
	
	$baneaxml_prod_ids = explode(',', Configuration::get('BANEAXML'));
	// XML:
	$xml = "<?xml version='1.0' encoding='utf-8'?>\n";
	$xml .= "<SHOP>\n";

	foreach ($baneaxml_prod_ids as $product_id) {
		
		global $cookie;
		$product = new Product($product_id, true, intval($cookie->id_lang));
		$link = new Link();
		$currency = new Currency((int)Tools::getValue('id_currency'));
		$img = Product::getCover($product->id);
	
		
		// parent category
		$cat = Db::getInstance()->executeS('
			SELECT cp.id_category, cl.link_rewrite AS link 
			FROM '._DB_PREFIX_.'category_product cp 
			LEFT JOIN '._DB_PREFIX_.'category_lang cl ON (cp.id_category = cl.id_category AND cl.id_lang = '. intval($cookie->id_lang) .') 
			WHERE cp.id_product = '. $product->id .' 
			ORDER BY cp.id_category DESC 
			LIMIT 1');

		$cat_id = $cat[0]['id_category'];
		
		// categories
		$result = Db::getInstance()->ExecuteS('
			SELECT c.id_category, c.id_parent, c.level_depth, cl.name 
			FROM '._DB_PREFIX_.'category c 
			JOIN '._DB_PREFIX_.'category_lang cl ON (cl.id_category = c.id_category AND cl.id_lang = '. intval($cookie->id_lang) .') 
			WHERE c.active = 1 AND c.level_depth > 0 
			ORDER BY level_depth, id_category');

		$categories = array();
		foreach ($result as $row) {
			if ($row['level_depth'] > 2) {
				$categories[$row['id_category']] = $categories[$row['id_parent']] . " | " . $row['name'];
				$categories[$row['id_category']] = $categories[$row['id_parent']] . " | " . $row['name'];
			} elseif ($row['level_depth'] > 1) {
				$categories[$row['id_category']] = $row['name'];
				$categories[$row['id_category']] = $row['name'];
			}
		}
		unset($result);
		
		$category_heureka = str_replace('&', '&amp;', $categories[$cat_id]);

		$xml .= "  <SHOPITEM>\n";
		$xml .= "    <ITEM_ID>".$product->id."</ITEM_ID>\n";
		$xml .= "    <PRODUCTNAME>".$product->name."</PRODUCTNAME>\n";
		$xml .= "    <URL>".$link->getProductLink($product, $product->link_rewrite, htmlspecialchars(strip_tags($product->category)), $product->ean13, intval($cookie->id_lang), (int)$this->context->shop->id, 0, true)."</URL>\n";
		$xml .= "    <IMGURL>".$this->context->link->getImageLink($product->link_rewrite, $product->id.'-'.(int)$img['id_image'], 'large_default')."</IMGURL>\n";
		$xml .= "    <PRICE_VAT>".number_format(Tools::convertPrice($product->getPrice(true, NULL, 2), $currency), 2, '.', '')."</PRICE_VAT>\n";
		$xml .= "    <MANUFACTURER>".htmlspecialchars(strip_tags(html_entity_decode($product->manufacturer_name, ENT_COMPAT, 'utf-8')))."</MANUFACTURER>\n";
		$xml .= "    <CATEGORYTEXT>". $category_heureka ."</CATEGORYTEXT>\n";
		$xml .= "</SHOPITEM>\n";
	}

	$xml .= "</SHOP>\n";

	return $xml;

}
	
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
*/
	
	// generates content for module settings in Administration of module page
	public function getContent()
	{
    $output = null;
		
    if (Tools::isSubmit('submit'.$this->name))
    {
			$baneaxml_value = strval(implode(Tools::getValue('baneaxml_prod_ids'), ','));
			if (!$baneaxml_value
			|| empty($baneaxml_value)
			|| !Validate::isGenericName($baneaxml_value))
			$output .= $this->displayError($this->l('Invalid Configuration value'));
			else
			{
				Configuration::updateValue('BANEAXML', $baneaxml_value);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
/*				
				// Generate and save XML file ///////////////////////////////////////////////

				$xml = $this->makeXML();
				$path = '../'.Configuration::get('BANEAXML_FOLDER');

				// xml folder
				if (!file_exists($path)) {
				mkdir($path, 0777);
				}
				// save the xml file
				if ($xml != '') {
					$file = fopen($path.'/banea_'. Configuration::get('BANEAXML_TOKEN') .'.xml','w');
					fwrite($file, $xml);
					fclose($file);
					$errors = false;
				} else {
					$errors = true;
				}

				if ($errors) {
					$output .= $this->displayError($this->l('Some error occurred.'));
				} else {
					$output .= $this->displayConfirmation($this->l('XML feed is successfuly generated!'));
				}

				///////////////////////////////////////////////////////////////////
*/
			}
    }
    return $output.$this->displayForm();
	}

}