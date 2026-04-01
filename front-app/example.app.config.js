module.exports = {
    apps: [
        {
            name: "Samba.ai Connector",
            script: "npm start",
            env: {
                NODE_ENV: "production",
                SHOPIFY_APP_URL: "https://shopify.sambaai.pl/app/,
                SHOPIFY_API_KEY: "a123", // from command "shopify app env show"
                SHOPIFY_API_SECRET: "123", // from command "shopify app env show"
                SCOPES: "read_customers,read_orders,read_products", // from shopify.app.toml
                PORT: "5000",  //same port as configured in nginx
                BACKEND_URL: "https://shopify.sambaai.pl",
                APP_EMBED_ID: "abc123-cba321,
            },
        },
    ],
};
