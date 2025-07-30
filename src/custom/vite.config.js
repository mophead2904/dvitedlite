import { defineConfig } from "vite"
import { resolve } from "node:path"
import postcssPresetEnv from "postcss-preset-env"

const port = 5173
const origin = `${process.env.DDEV_PRIMARY_URL}:${port}`

// if process.env.DDEV_PRIMARY_URL is undefined, throw an error
if (!process.env.DDEV_PRIMARY_URL) {
  throw new Error("DDEV_PRIMARY_URL environment variable is not set. \n Run ddev 'npm run [COMMAND]' instead")
}

export default defineConfig(({ mode }) => ({
  plugins: [
    {
      name: "drupal-template-watcher",
      handleHotUpdate({ file, server }) {
        // Only trigger page reload for template files, not CSS/JS
        if (file.match(/\.(php|inc|theme|twig)$/)) {
          server.ws.send({
            type: "full-reload",
          })
        }
        // Let Vite handle CSS/JS with HMR
        return undefined
      },
    },
    postcssPresetEnv({
      stage: 3,
      features: {
        "nesting-rules": true,
        "custom-media-queries": true,
      },
    }),
  ],
  build: {
    manifest: true,
    lib: {
      entry: resolve(__dirname, "./src/js/main.js"),
      name: "vite",
      fileName: "main",
      formats: ["es"],
    },
    cssCodeSplit: true,
    outDir: resolve(import.meta.dirname, "./dist"),
    sourcemap: mode === "development",
  },
  server: {
    host: "0.0.0.0",
    port: port,
    origin: origin,
    strictPort: true,
    cors: {
      origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
    },
  },
}))
