import fs from "node:fs"
import path from "node:path"

const src = path.resolve("node_modules/alpinejs/dist/cdn.min.js")
const dst = path.resolve("public_html/assets/js/alpine.min.js")

await fs.promises.copyFile(src, dst)

