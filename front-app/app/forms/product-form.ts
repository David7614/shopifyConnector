import { authenticate } from "../shopify.server";
import { getUser } from "app/models/user.server";
import { setConfig } from "app/models/user-config.server";
import { productFieldsToHandle } from "app/utils/forms";

export default async function productForm (request: Request, formData: any) {
  const { session } = await authenticate.admin(request);

  try {
    const username = session.shop;

    const user = await getUser(username);

    if (!user) {
      throw new Error('User not exist')
    }

    productFieldsToHandle.forEach((key: string) => {
      const field = formData.get(key)

      if (field === null) {
        setConfig(user.id, key, '0')
        return
      }

      setConfig(user.id, key, parseInt(field).toString())
    })

    return {
      status: { type: "success", message: "Successfully saved product data" },
    };
  } catch (e) {
    return {
      status: { type: "error", message: "Error when saving product data" },
    };
  }
}

