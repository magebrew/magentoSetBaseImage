<?php

/**
 * Resource model for setting attribute value
 *
 * @category   Magebrew
 * @author     Magebrew <magebrew.com>
 */
class Magebrew_SetImage_Model_Resource_Catalog_Product extends Mage_Catalog_Model_Resource_Product
{
    /**
     * Insert or Update attribute data
     *
     * @param Mage_Catalog_Model_Abstract $object
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param mixed $value
     * @return Mage_Catalog_Model_Resource_Abstract
     */
    protected function _saveAttributeValue($object, $attribute, $value)
    {
        $write = $this->_getWriteAdapter();
        //set default store id
        $storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
        $table = $attribute->getBackend()->getTable();

        /**
         * If we work in single store mode all values should be saved just
         * for default store id
         * In this case we clear all not default values
         */
        if (Mage::app()->isSingleStoreMode()) {
            $storeId = $this->getDefaultStoreId();
            $write->delete($table, array(
                'attribute_id = ?' => $attribute->getAttributeId(),
                'entity_id = ?' => $object->getEntityId(),
                'store_id <> ?' => $storeId
            ));
        }

        $data = new Varien_Object(array(
            'entity_type_id' => $attribute->getEntityTypeId(),
            'attribute_id' => $attribute->getAttributeId(),
            'store_id' => $storeId,
            'entity_id' => $object->getEntityId(),
            'value' => $this->_prepareValueForSave($value, $attribute)
        ));
        $bind = $this->_prepareDataForTable($data, $table);

        if ($attribute->isScopeStore()) {
            /**
             * Update attribute value for store
             */
            $this->_attributeValuesToSave[$table][] = $bind;
        } else if ($attribute->isScopeWebsite() && $storeId != $this->getDefaultStoreId()) {
            /**
             * Update attribute value for website
             */
            $storeIds = Mage::app()->getStore($storeId)->getWebsite()->getStoreIds(true);
            foreach ($storeIds as $storeId) {
                $bind['store_id'] = (int)$storeId;
                $this->_attributeValuesToSave[$table][] = $bind;
            }
        } else {
            /**
             * Update global attribute value
             */
            $bind['store_id'] = $this->getDefaultStoreId();
            $this->_attributeValuesToSave[$table][] = $bind;
        }

        return $this;
    }
}