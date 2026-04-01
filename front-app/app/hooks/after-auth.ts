import type { SessionStorage } from "@shopify/shopify-app-session-storage";
import type { ShopifyRestResources } from "node_modules/@shopify/shopify-api/dist/ts/rest/types";
import type {
  AfterAuthOptions,
  AppConfigArg,
} from "node_modules/@shopify/shopify-app-remix/dist/ts/server/config-types";

export default async function afterAuthHook(
  ctx: AfterAuthOptions<
    AppConfigArg<
      ShopifyRestResources,
      SessionStorage,
      { unstable_newEmbeddedAuthStrategy: true; removeRest: true }
    >,
    ShopifyRestResources
  >,
) {
  //
}
