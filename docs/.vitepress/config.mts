import { defineConfig } from 'vitepress'

const description =
  'A lightweight, self-hosted, Pusher-compatible realtime broadcasting server for Laravel. Point Echo at your own server — zero frontend changes.'

export default defineConfig({
  title: 'Surge',
  description,
  lang: 'en-US',
  // GitHub Pages project site lives under /Surge/.
  base: '/Surge/',
  cleanUrls: true,
  lastUpdated: true,

  head: [
    ['meta', { name: 'theme-color', content: '#7c6cff' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Surge — realtime for Laravel' }],
    ['meta', { property: 'og:description', content: description }],
    [
      'link',
      {
        rel: 'icon',
        href:
          'data:image/svg+xml,' +
          encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="%237c6cff"/><stop offset="1" stop-color="%2322d3ee"/></linearGradient></defs><path fill="url(%23g)" d="M13 2 4 14h6l-1 8 9-12h-6z"/></svg>',
          ),
      },
    ],
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    [
      'link',
      {
        rel: 'stylesheet',
        href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
      },
    ],
  ],

  themeConfig: {
    logo: { src: '/logo.svg', alt: 'Surge' },
    siteTitle: 'Surge',

    nav: [
      { text: 'Guide', link: '/guide/introduction', activeMatch: '/guide/' },
      { text: 'Reference', link: '/reference/configuration', activeMatch: '/reference/' },
      {
        text: 'v0.1.0',
        items: [
          { text: 'Changelog', link: 'https://github.com/nowshad7/Surge/commits/main' },
          { text: 'Turbo runtime (experimental)', link: '/guide/runtimes#turbo' },
        ],
      },
    ],

    sidebar: {
      '/': [
        {
          text: 'Getting started',
          collapsed: false,
          items: [
            { text: 'Introduction', link: '/guide/introduction' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Quickstart', link: '/guide/quickstart' },
          ],
        },
        {
          text: 'Using Surge',
          collapsed: false,
          items: [
            { text: 'Laravel & Echo', link: '/guide/laravel' },
            { text: 'Channels', link: '/guide/channels' },
            { text: 'Client events', link: '/guide/client-events' },
            { text: 'Broadcasting events', link: '/guide/broadcasting' },
          ],
        },
        {
          text: 'Operating Surge',
          collapsed: false,
          items: [
            { text: 'Runtimes', link: '/guide/runtimes' },
            { text: 'Scaling with Redis', link: '/guide/scaling' },
            { text: 'Deployment', link: '/guide/deployment' },
            { text: 'Architecture', link: '/guide/architecture' },
          ],
        },
        {
          text: 'Reference',
          collapsed: false,
          items: [
            { text: 'Configuration', link: '/reference/configuration' },
            { text: 'CLI', link: '/reference/cli' },
          ],
        },
      ],
    },

    socialLinks: [{ icon: 'github', link: 'https://github.com/nowshad7/Surge' }],

    search: { provider: 'local' },

    editLink: {
      pattern: 'https://github.com/nowshad7/Surge/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    outline: { level: [2, 3], label: 'On this page' },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Zero required dependencies · © nowshad7',
    },
  },

  markdown: {
    theme: { light: 'github-light', dark: 'github-dark' },
    lineNumbers: true,
  },

  sitemap: { hostname: 'https://nowshad7.github.io/Surge/' },
})
