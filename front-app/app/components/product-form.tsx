import {
  Button,
  FormLayout,
  Text,
  Box,
  Checkbox,
} from "@shopify/polaris";

import { Form } from "@remix-run/react";
import { useCallback, useEffect, useState } from "react";
import { EFieldType, EProductFields, FormNames, getFieldValue } from "app/utils/forms";

interface IProps {
  initFields: any;
  isSaved: boolean;
  onSave: () => void;
  onContinue: () => void;
}

export default function ProductForm({ initFields, isSaved, onSave, onContinue }: IProps) {
  const [productImage, setProductImage] = useState(false);
  const [productDescription, setProductDescription] = useState(false);
  const [productBrand, setProductBrand] = useState(false);
  const [productStock, setProductStock] = useState(false);
  const [productPriceBeforeDiscount, setProductPriceBeforeDiscount] = useState(false);
  const [productCategory, setProductCategory] = useState(false);
  const [productVariants, setProductVariants] = useState(false);
  const [productParameters, setProductParameters] = useState(false);

  const handleProductImageChange = useCallback(
    (newChecked: boolean) => setProductImage(newChecked),
    [],
  );

  const handleProductDescriptionChange = useCallback(
    (newChecked: boolean) => setProductDescription(newChecked),
    [],
  );

  const handleProductBrandChange = useCallback(
    (newChecked: boolean) => setProductBrand(newChecked),
    [],
  );

  const handleProductStockChange = useCallback(
    (newChecked: boolean) => setProductStock(newChecked),
    [],
  );

  const handleProductPriceBeforeDiscountChange = useCallback(
    (newChecked: boolean) => setProductPriceBeforeDiscount(newChecked),
    [],
  );

  const handleProductCategoryChange = useCallback(
    (newChecked: boolean) => setProductCategory(newChecked),
    [],
  );

  const handleProductVariantsChange = useCallback(
    (newChecked: boolean) => setProductVariants(newChecked),
    [],
  );

  const handleProductParametersChange = useCallback(
    (newChecked: boolean) => setProductParameters(newChecked),
    [],
  );

  useEffect(() => {
    setProductImage(getFieldValue(initFields, EProductFields.PRODUCT_IMAGE, EFieldType.BOOL))
    setProductDescription(getFieldValue(initFields, EProductFields.PRODUCT_DESCRIPTION, EFieldType.BOOL))
    setProductBrand(getFieldValue(initFields, EProductFields.PRODUCT_BRAND, EFieldType.BOOL))
    setProductStock(getFieldValue(initFields, EProductFields.PRODUCT_STOCK, EFieldType.BOOL))
    setProductPriceBeforeDiscount(getFieldValue(initFields, EProductFields.PRODUCT_PRICE_BEFORE_DISCOUNT, EFieldType.BOOL))
    setProductCategory(getFieldValue(initFields, EProductFields.PRODUCT_CATEGORY, EFieldType.BOOL))
    setProductVariants(getFieldValue(initFields, EProductFields.PRODUCT_VARIANTS, EFieldType.BOOL))
    setProductParameters(getFieldValue(initFields, EProductFields.PRODUCT_PARAMETERS, EFieldType.BOOL))
  }, [initFields])

  return (
    <Box padding="0">
      <div className="flex flex-col gap-4">
        <Text variant="headingXl" as="h4">
          Product feed
        </Text>

        <Text as="p" tone="subdued" variant="bodyLg">
          Select which data should be exported in the product feed.
        </Text>

        <div>
          <Form method="post">
            <FormLayout>
              <input name="formName" type="hidden" value={FormNames.PRODUCT_FORM} />

              <div className="grid divide-y">
                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_IMAGE}
                    label="Image"
                    checked={productImage}
                    value={Number(productImage).toString()}
                    onChange={handleProductImageChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_DESCRIPTION}
                    label="Product description"
                    checked={productDescription}
                    value={Number(productDescription).toString()}
                    onChange={handleProductDescriptionChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_BRAND}
                    label="Product brand"
                    checked={productBrand}
                    value={Number(productBrand).toString()}
                    onChange={handleProductBrandChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_STOCK}
                    label="Product stock"
                    checked={productStock}
                    value={Number(productStock).toString()}
                    onChange={handleProductStockChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_PRICE_BEFORE_DISCOUNT}
                    label="Price before discount"
                    checked={productPriceBeforeDiscount}
                    value={Number(productPriceBeforeDiscount).toString()}
                    onChange={handleProductPriceBeforeDiscountChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_CATEGORY}
                    label="Category"
                    checked={productCategory}
                    value={Number(productCategory).toString()}
                    onChange={handleProductCategoryChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_VARIANTS}
                    label="Product variants"
                    checked={productVariants}
                    value={Number(productVariants).toString()}
                    onChange={handleProductVariantsChange}
                  />
                </div>

                <div className="py-2">
                  <Checkbox
                    name={EProductFields.PRODUCT_PARAMETERS}
                    label="Product parameters"
                    checked={productParameters}
                    value={Number(productParameters).toString()}
                    onChange={handleProductParametersChange}
                  />
                </div>
              </div>

              <Button 
                submit={isSaved ? true : false} 
                size="large" 
                variant={isSaved ? "primary" : "secondary"} 
                onClick={() => {
                  if (isSaved) {
                    onContinue()
                    return
                  }

                  if (!isSaved) {
                    onSave()
                  }
                }}
              >
                {isSaved ? 'Continue' : 'Save'}
              </Button>
            </FormLayout>
          </Form>
        </div>
      </div>
    </Box>
  );
}
