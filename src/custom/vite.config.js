import { defineConfig } from "vite"
import path from "path"
import { glob } from "glob"

const postcssGlobalData = require("@csstools/postcss-global-data")
const isProduction = process.env.NODE_ENV === "production"

// Dynamically find all JS files and create input object
function getJSEntries() {
  const jsFiles = glob.sync("./src/js/**/*.js")
  const entries = {}

  jsFiles.forEach((file) => {
    const name = path.basename(file, ".js")
    entries[name] = file
  })

  return entries
}

// Dynamically find all CSS files for individual loading in development
function getIndividualCSSEntries() {
  const cssFiles = glob.sync("./src/css/**/*.css", {
    ignore: [
      "./src/css/main.css", // Exclude the main file since it imports everything
    ],
  })
  const entries = {}

  cssFiles.forEach((file) => {
    const name = path.basename(file, ".css")
    entries[`css-${name}`] = "./" + file
  })

  console.log(entries)
  return entries
}

// Get the appropriate CSS entries based on environment
function getCSSEntries() {
  console.log(process.env.NODE_ENV, isProduction)
  if (isProduction) {
    // Production: Use bundled main.css
    return {
      main: "./src/css/main.css",
    }
  } else {
    // Development: Use individual CSS files for better HMR
    return {
      // Include base styles that others depend on
      main: "./src/css/main-base.css", // We'll create this
      ...getIndividualCSSEntries(),
    }
  }
}

export default defineConfig({

  plugins: [
    // Custom plugin to trigger Drupal cache clear on template changes
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
  ],

  css: {
    // Enable source maps for better debugging
    devSourcemap: true,
    postcss: {
      plugins: [
        require("postcss-import")({
          // Optimize import resolution
          resolve: (id, basedir) => {
            // Handle relative imports more efficiently
            if (id.startsWith("./")) {
              return path.resolve(basedir, id)
            }
            return id
          },
        }),
        postcssGlobalData({
          files: ["./src/css/utility.css"],
        }),
        require("postcss-custom-media")(),
        require("postcss-nesting"),
        require("postcss-preset-env")({
          stage: 3,
          features: {
            "nesting-rules": true,
            "custom-media-queries": true,
          },
        }),
      ],
    },
    // Enable CSS code splitting for better HMR
    codeSplit: true,
    // Preprocess CSS more efficiently in development
    preprocessorOptions: {
      css: {
        charset: false,
      },
    },
  },

  build: {
    manifest: true,
    outDir: path.resolve(__dirname, "dist"),
    // Optimize CSS output
    cssCodeSplit: true,
    rollupOptions: {
      input: {
        // CSS entries (individual in dev, bundled in prod)
        ...getCSSEntries(),

        // JS entries
        ...getJSEntries(),
      },
      output: {
        entryFileNames: "js/[name].js",
        chunkFileNames: "js/chunks/[name]-[hash].js",
        assetFileNames: (assetInfo) => {
          const assetName = assetInfo.name || ""
          if (assetName.endsWith(".css")) {
            const justTheFileName = path.basename(assetName)
            return `css/${justTheFileName}`
          }
          if (/\.(woff2?|eot|ttf|otf)$/i.test(assetName)) {
            return "fonts/[name][extname]"
          }
          if (/\.(png|jpe?g|gif|svg|webp)$/i.test(assetName)) {
            return "images/[name]-[hash][extname]"
          }
          return "assets/[name]-[hash][extname]"
        },
      },
    },
  },

  server: {
    host: "0.0.0.0",
    port: 12321,
    cors: {
      origin: "*",
      methods: ["GET", "HEAD", "PUT", "POST", "DELETE", "PATCH", "OPTIONS"],
      credentials: true,
    },
    strictPort: true,
    hmr: {
      host: "localhost",
      port: 12321,
      clientPort: 12321,
      // Optimize HMR
      overlay: true, // Disable error overlay for faster updates
    },
    // Optimize for faster responses
    fs: {
      strict: false,
    },
    // Improve dev server performance
    middlewareMode: false,
  },

  // Optimize dependency pre-bundling
  optimizeDeps: {
    exclude: ["@vite/client", "@vite/env"],
  },

  // Configure externals for Drupal dependencies
  external: ["jquery", "jQuery", "Drupal", "drupalSettings"],
})
