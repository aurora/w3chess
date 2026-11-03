import { defineConfig } from 'vite'

// The API target can be overridden to run a second stack beside a running
// development environment: W3CHESS_API_URL=http://localhost:5010 npx vite
const api = process.env.W3CHESS_API_URL || 'http://localhost:5000'

export default defineConfig({
  server: {
    port: 5173,
    proxy: {
      '/api': api
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true
  }
})
