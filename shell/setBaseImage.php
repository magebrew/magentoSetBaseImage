<?php
/**
 * Set image script
 *
 * @category   Magebrew
 * @author     Magebrew <magebrew.com>
 */

require_once 'abstract.php';

class Magebrew_Set_Image extends Mage_Shell_Abstract
{
    /**
     * Run script
     *
     */
    public function run()
    {
        $installer = new Mage_Catalog_Model_Resource_Setup('core_setup');
        try {
            //grab all products ids that have 'no_selection' value in database
            $noSelectionSelect = $installer->getConnection()->select()
                ->from(
                    array('product' => $installer->getTable('catalog/product')),
                    array('id' => 'product.entity_id')
                )
                ->join(
                    array('varchar_table' => $installer->getTable('catalog_product_entity_varchar')),
                    'varchar_table.entity_id = product.entity_id',
                    array()
                )
                ->join(
                    array('eav_table' => $installer->getTable('eav/attribute')),
                    'varchar_table.attribute_id = eav_table.attribute_id',
                    array()
                )
                ->where('eav_table.attribute_code = "image" AND eav_table.entity_type_id = 4 AND varchar_table.value = "no_selection" AND varchar_table.store_id = 0');

            //get products ids that have missing image value record
            $missingImageValueSelectInner = $installer->getConnection()->select()
                ->from(
                    array('product' => $installer->getTable('catalog/product')),
                    array('id' => 'product.entity_id')
                )
                ->join(
                    array('varchar_table' => $installer->getTable('catalog_product_entity_varchar')),
                    'varchar_table.entity_id = product.entity_id',
                    array()
                )
                ->join(
                    array('eav_table' => $installer->getTable('eav/attribute')),
                    'varchar_table.attribute_id = eav_table.attribute_id',
                    array()
                )
                ->where('eav_table.attribute_code = "image" AND eav_table.entity_type_id = 4 AND varchar_table.store_id = 0');

            $missingImageValueSelect = $installer->getConnection()->select()
                ->from(
                    array('product' => $installer->getTable('catalog/product')),
                    array('id' => 'product.entity_id')
                )
                ->where('product.entity_id NOT IN ?', $missingImageValueSelectInner);

            //combine ids.
            $unitedSselect = $installer->getConnection()->select()->union(array($noSelectionSelect, $missingImageValueSelect));
            $ids = $installer->getConnection()->fetchCol($unitedSselect);
            Mage::log(sprintf("found %s candidats for setting image", count($ids)), null, 'image_set.log', true);

            //Get id to image relation from catalog_product_entity_media_gallery table
            $mediaSelect = $installer->getConnection()->select()
                ->from(array('gallery' => $installer->getTable(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE)), array('id' => 'entity_id', 'value'))
                ->join(
                    array('gallery_value' => $installer->getTable(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_VALUE_TABLE)),
                    'gallery_value.value_id = gallery.value_id AND store_id = 0',
                    array()
                )
                ->where('entity_id in (?)', $ids)
                ->order('position asc');
            $query = $installer->getConnection()->query($mediaSelect);
            $idToImage = array();
            while ($row = $query->fetch()) {
                if (!isset($idToImage[$row['id']])) {
                    $idToImage[$row['id']] = $row['value'];
                }
            }

            //Mage::log($idToImage, null, 'image_set.log', true);
            /** @var Magebrew_SetImage_Model_Resource_Catalog_Product $resource */
            $resource = Mage::getModel('magebrew_setimage/resource_catalog_product');
            foreach ($idToImage as $id => $image) {
                $product = Mage::getModel('catalog/product')->setId($id);
                $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
                $product->setImage($image);
                $product->setSmallImage($image);
                $product->setThumbnail($image);
                Mage::log(sprintf("trying to set image %s to product %s", $image, $id), null, 'image_set.log', true);
                $resource->saveAttribute($product, 'image');
                $resource->saveAttribute($product, 'small_image');
                $resource->saveAttribute($product, 'thumbnail');
                echo 'set images for product ' . $id . PHP_EOL;
            }
            echo 'Finished' . PHP_EOL;
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}

$shell = new Magebrew_Set_Image();
$shell->run();
