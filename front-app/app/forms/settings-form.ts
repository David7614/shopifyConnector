import { authenticate } from "../shopify.server";
import { getUser } from "app/models/user.server";
import { setConfig } from "app/models/user-config.server";
import { ESettingsFields, settingsFieldsToHandle } from "app/utils/forms";
import { setIntegrationData } from "app/models/integration-data.servers";
import { format } from "date-fns";

const METAFIELD_NAMESPACE = "tracking_scripts";

const getDate = (years = 5) => {
  const date = new Date();
  date.setFullYear(date.getFullYear() - years);
  return date.toISOString().split("T")[0]; // "YYYY-MM-DD"
}

const getOrdersFromDate = (value: any) => {
  return format(new Date(value), 'yyyy-MM-dd')
}

const setFullDatabaseExport = async (userId: any, formData: any) => {
  let lastDate = getDate(20)

  const dateFrom = formData.get(ESettingsFields.ORDERS_DATE_FROM)

  if (dateFrom) {
    lastDate = getOrdersFromDate(dateFrom)
  }

  setIntegrationData(userId, 'last_orders_integration_date', lastDate) 
  setIntegrationData(userId, 'last_customer_integration_date', getDate(20)) 
  setIntegrationData(userId, 'last_products_integration_date', getDate(20)) 
}

export default async function settingsForm (request: Request, formData: any) {
  const { admin, session } = await authenticate.admin(request);

  try {
    const username = session.shop;

    const user = await getUser(username);

    if (!user) {
      throw new Error('User not exist')
    }

    const trackpoint = formData.get('trackpoint')

    if (trackpoint !== null) {
      if (trackpoint === '') {
        removeAppMetafields(admin, [{ key: 'trackpoint', value: trackpoint }])
        setConfig(user.id, 'trackpoint', '');
      } else {
        createOrUpdateAppMetafields(admin, [{ key: 'trackpoint', value: trackpoint }])
        setConfig(user.id, 'trackpoint', trackpoint);
      }
    }

    const smartpoint = formData.get('smartpoint')

    if (smartpoint !== null) {
      if (smartpoint === '0') {
        removeAppMetafields(admin, [{ key: 'smartpoint', value: smartpoint }])
        setConfig(user.id, 'smartpoint', '0');
      } else {
        createOrUpdateAppMetafields(admin, [{ key: 'smartpoint', value: smartpoint }])
        setConfig(user.id, 'smartpoint', smartpoint);
      }
    }

    settingsFieldsToHandle.forEach(async (key: string) => {
      const field = formData.get(key)

      if (field === null) {
        await setConfig(user.id, key, '0')

        if (key === ESettingsFields.EXPORT_TYPE && field === '0') {
          await setFullDatabaseExport(user.id, formData)
        }

        return
      }

      if (key === ESettingsFields.ORDERS_DATE_FROM) {
        await setConfig(user.id, key, getOrdersFromDate(field))
        return
      }

      if (key === ESettingsFields.EXPORT_TYPE && field === '0') {
        await setFullDatabaseExport(user.id, formData)
      }

      await setConfig(user.id, key, field)
    })

    return {
      status: { type: "success", message: "Successfully saved settings data" },
    };
  } catch (e) {
    return {
      status: { type: "error", message: "Error when saving settings data" },
    };
  }
}

async function createOrUpdateAppMetafields(admin: any, items: any[]) {
  const GET_OWNER_ID = `
    query getOwnerID {
      currentAppInstallation {
        id
      }
    }
  `;

  const ownerResponse = await admin.graphql(GET_OWNER_ID);
  const ownerJson = await ownerResponse.json();
  const ownerId = ownerJson.data.currentAppInstallation.id;

  const METAFIELD_MUTATION = `
    mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
      metafieldsSet(metafields: $metafields) {
        metafields {
          key
          value
        }
        userErrors {
          field
          message
        }
      }
    }
  `;

  const input = {
    metafields: items.map((item) => ({
      namespace: METAFIELD_NAMESPACE,
      key: item.key,
      value: item.value,
      type: "single_line_text_field",
      ownerId: ownerId,
    })),
  };

  const response = await admin.graphql(METAFIELD_MUTATION, {
    variables: {
      metafields: input.metafields,
    },
  });

  const responseJson = await response.json();

  if (responseJson.data?.metafieldsSet?.userErrors?.length > 0) {
    throw new Error(responseJson.data.metafieldsSet.userErrors[0].message);
  }

  return responseJson.data.metafieldsSet.metafields;
}

async function removeAppMetafields(admin: any, items: any[]) {
  const GET_OWNER_ID = `
    query getOwnerID {
      currentAppInstallation {
        id
      }
    }
  `;

  const ownerResponse = await admin.graphql(GET_OWNER_ID);
  const ownerJson = await ownerResponse.json();
  const ownerId = ownerJson.data.currentAppInstallation.id;

  const METAFIELD_MUTATION = `
    mutation metafieldsDelete($metafields: [MetafieldIdentifierInput!]!) {
      metafieldsDelete(metafields: $metafields) {
        deletedMetafields {
          namespace
          key
          ownerId
        }
        userErrors {
          field
          message
        }
      }
    }
  `;

  const input = {
    metafields: items.map((item) => ({
      namespace: METAFIELD_NAMESPACE,
      key: item.key,
      ownerId: ownerId,
    })),
  };

  const response = await admin.graphql(METAFIELD_MUTATION, {
    variables: {
      metafields: input.metafields,
    },
  });

  const responseJson = await response.json();

  if (responseJson.data?.metafieldsSet?.userErrors?.length > 0) {
    throw new Error(responseJson.data.metafieldsSet.userErrors[0].message);
  }

  return responseJson.data.metafieldsDelete.deletedMetafields;
}

