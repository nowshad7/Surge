import DefaultTheme from 'vitepress/theme'
import type { Theme } from 'vitepress'
import './styles/custom.css'

// Keep the theme itself stock (safe SSR) and do all the personality in CSS.
const theme: Theme = {
  extends: DefaultTheme,
}

export default theme
