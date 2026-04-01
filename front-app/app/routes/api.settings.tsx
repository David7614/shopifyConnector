import type { ActionFunctionArgs } from "@remix-run/node"

// export const loader = () => {
//     const body = JSON.stringify({
//         hello: "world"
//     })
//     return new Response(body, {
//         status: 200
//     })
// }

export const action = async (args: ActionFunctionArgs) => {
    // const { trackpoint } = await args.request.json()

    // const body = JSON.stringify({
    //     // hello: trackpoint,
    //     hello: 'test',
    // })

    // return new Response(body, { status: 200 })
    return new Response(null, { status: 200 })
}
