import { authenticate } from "../shopify.server";
import { createInitialQueue, getUser } from "app/models/user.server";
import { getConfig, setConfig } from "app/models/user-config.server";
import { customerFieldsToHandle } from "app/utils/forms";

export default async function customerForm (request: Request, formData: any) {
  const { session } = await authenticate.admin(request);

  try {
    const username = session.shop;

    const user = await getUser(username);

    if (!user) {
      throw new Error('User not exist')
    }

    customerFieldsToHandle.forEach((key: string) => {
      const field = formData.get(key)

      if (field === null) {
        setConfig(user.id, key, '0')
        return
      }

      setConfig(user.id, key, parseInt(field).toString())
    })

    const feedEnabled = await getConfig(user.id, "feed_enabled")
    const initialQueueDone = await getConfig(user.id, "initial_queue_done")

    if (feedEnabled?.value === '1' && initialQueueDone?.value !== '1') {
      await createInitialQueue(user.id)
    }

    return {
      status: { type: "success", message: "Successfully saved customer data" },
    };
  } catch (e) {
    return {
      status: { type: "error", message: "Error when saving customer data" },
    };
  }
}

