import type { ActionFunctionArgs } from "@remix-run/node";
import { authenticate } from "../shopify.server";
import { deleteCustomerData, deleteShop, getCustomerData } from "app/models/customer.server";

export const action = async ({ request }: ActionFunctionArgs) => {
	try {
		// Clone the request before passing it to authenticate.webhook()
		const requestClone = request.clone();

		// Get the raw body first
		const rawBody = await request.text();
		const payload = JSON.parse(rawBody);

		try {
			// Now use the cloned request for authentication
			const { shop, topic } = await authenticate.webhook(requestClone);

			console.log(`Received ${topic} webhook from ${shop}`);
			console.log("Payload:", JSON.stringify(payload, null, 2));

			switch (topic) {
				case "customers/data_request":
					const customerData = await getCustomerData(payload);

					if (!customerData) {
						console.log(`No customer data stored. Responding to data request for shop: ${shop}`);
						break;
					}

					console.log(`Customer data found. Responding to data request for shop: ${shop}`);
					return new Response("Webhook received", { status: 200 });

				case "customers/redact":
					const deleteCustomerResult = await deleteCustomerData(payload)

					if (!deleteCustomerResult) {
						console.log(`No customer data stored. Ignoring redaction request for shop: ${shop}`);
					}

					console.log(`Customer data deleted. Responding to data request for shop: ${shop}`);
					break;

				case "shop/redact":
					const deleteShopResult = await deleteShop(payload)

					if (!deleteShopResult) {
						console.log(`No shop data stored. Acknowledging shop deletion request for shop: ${shop}`);
					}

					console.log(`Shop data deleted. Responding to data request for shop: ${shop}`);
					break;

				default:
					console.warn(`❌ Unhandled webhook topic: ${topic}`);
					return new Response("Unhandled webhook topic", { status: 400 });
			}

			// Return 200 only if authentication and processing succeeded
			return new Response("Webhook received", { status: 200 });
		} catch (authError) {
			// Specifically handle HMAC validation failures
			console.error("🔒 Webhook authentication failed:", authError);
			return new Response("Webhook HMAC validation failed", { status: 401 });
		}
  	} catch (error) {
    	// Handle other types of errors (like JSON parsing)
    	console.error("🚨 Webhook processing error:", error);
    	return new Response("Error processing webhook", { status: 500 });
  	}
};
