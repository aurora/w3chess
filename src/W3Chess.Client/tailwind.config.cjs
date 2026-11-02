/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{fs,fs.js,js}'],
  theme: {
    extend: {
      colors: {
        surface: 'var(--surface)',
        'surface-container-lowest': 'var(--surface-container-lowest)',
        'surface-container-low': 'var(--surface-container-low)',
        'surface-container': 'var(--surface-container)',
        'surface-container-high': 'var(--surface-container-high)',
        'surface-container-highest': 'var(--surface-container-highest)',
        'on-surface': 'var(--on-surface)',
        'on-surface-variant': 'var(--on-surface-variant)',
        outline: 'var(--outline)',
        'outline-variant': 'var(--outline-variant)',
        primary: 'var(--primary)',
        'on-primary': 'var(--on-primary)',
        secondary: 'var(--secondary)',
        'on-secondary': 'var(--on-secondary)',
        error: 'var(--error)',
        background: 'var(--background)',
        'on-background': 'var(--on-background)',
        'board-light': 'var(--board-light)',
        'board-dark': 'var(--board-dark)',
        accent: 'var(--accent)'
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace']
      },
      borderRadius: {
        DEFAULT: '0.25rem'
      }
    }
  },
  plugins: []
}
