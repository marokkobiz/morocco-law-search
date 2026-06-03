import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  root: "src/frontend",
  plugins: [react()],
  publicDir: false,
  build: {
    outDir: "../public",
    emptyOutDir: false,
    rollupOptions: {
      output: {
        entryFileNames: "assets/app.js",
        chunkFileNames: "assets/[name].js",
        assetFileNames: "assets/[name][extname]"
      }
    }
  },
  server: {
    proxy: {
      "/api": "http://localhost:3000",
      "/marokko-biz-icon.png": "http://localhost:3000"
    }
  }
});
