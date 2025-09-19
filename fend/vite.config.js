import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  base: "/app", // where the app will be served under Laravel public
  build: {
    outDir: "../bend/public/app", // output INTO Laravel public
    emptyOutDir: true,
  },
  server: {
    host: true, // optional; helps when testing
  },
  // server: {
  //   host: '127.0.0.1',
  //   port: 5173,
  // },
});
