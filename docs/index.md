---
layout: home

hero:
  name: Surge
  text: Realtime for Laravel, self-hosted.
  tagline: A lightweight, Pusher-compatible WebSocket server. Point Laravel Echo at your own machine — no code changes, no per-message bills, zero required dependencies.
  image:
    src: /logo.svg
    alt: Surge
  actions:
    - theme: brand
      text: Get started
      link: /guide/introduction
    - theme: alt
      text: Quickstart
      link: /guide/quickstart
    - theme: alt
      text: View on GitHub
      link: https://github.com/nowshad7/Surge

features:
  - icon: 🔌
    title: Drop-in for Laravel
    details: Speaks the Pusher protocol, so the stock pusher broadcaster and Laravel Echo work unchanged. Repoint host and port — done.
  - icon: 🧱
    title: Fails closed
    details: A real RFC 6455 core that rejects malformed, unmasked, or oversized frames instead of mangling them. Every signature is verified in constant time.
  - icon: 🪶
    title: Light by default
    details: The lite runtime is pure PHP with zero required dependencies. One command to run, nothing to compile, runs anywhere PHP runs.
  - icon: 👥
    title: Presence & private channels
    details: Public, private, and presence channels with member tracking and client events — authorized by the same HMAC signature Laravel already generates.
  - icon: 📡
    title: Scales horizontally
    details: Optional Redis pub/sub relay across nodes, implemented as a RESP client over raw sockets. No extension required.
  - icon: ⚙️
    title: Own your infrastructure
    details: No usage caps, no third party seeing your traffic, no surprise bills. Run it next to your app.
---

<div class="surge-section">
  <p class="surge-eyebrow">Why Surge</p>
  <h2 class="surge-h">Pusher's developer experience, your server</h2>
  <p class="surge-sub">Surge keeps everything you like about hosted realtime — Laravel Echo, private and presence channels, <code>broadcast()</code> — and moves it onto infrastructure you control.</p>

  <table class="surge-compare">
    <thead>
      <tr>
        <th>&nbsp;</th>
        <th>Pusher (hosted)</th>
        <th>Laravel Reverb</th>
        <th class="surge-col-surge">Surge</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>Self-hosted</td><td class="surge-no">No</td><td class="surge-yes">Yes</td><td class="surge-yes">Yes</td></tr>
      <tr><td>Works with Laravel Echo unchanged</td><td class="surge-yes">Yes</td><td class="surge-yes">Yes</td><td class="surge-yes">Yes</td></tr>
      <tr><td>Required dependencies to start</td><td>Account</td><td>PHP + ext</td><td class="surge-col-surge"><strong>None</strong></td></tr>
      <tr><td>Per-message pricing</td><td>Yes</td><td class="surge-no">No</td><td class="surge-no">No</td></tr>
      <tr><td>Multi-core runtime</td><td>n/a</td><td class="surge-no">No</td><td>Experimental</td></tr>
    </tbody>
  </table>
</div>

<div class="surge-section" style="margin-top: 64px;">
  <p class="surge-eyebrow">How it feels</p>
  <h2 class="surge-h">Three steps to realtime</h2>
  <div class="surge-steps">
    <div class="surge-step">
      <div class="n">1</div>
      <h4>Install</h4>
      <p><code>composer require nsd7/surge</code> — the service provider auto-registers.</p>
    </div>
    <div class="surge-step">
      <div class="n">2</div>
      <h4>Point Echo at Surge</h4>
      <p>Set the host and port in your broadcasting and Echo config. No code changes.</p>
    </div>
    <div class="surge-step">
      <div class="n">3</div>
      <h4>Serve & broadcast</h4>
      <p><code>php artisan surge:serve</code>, then <code>broadcast()</code> as you always have.</p>
    </div>
  </div>
</div>

<div class="surge-section" style="margin-top: 64px;">
  <div class="surge-cta">
    <h3>Ship realtime this afternoon.</h3>
    <p class="surge-sub" style="margin: 0 auto;">Start with the lite runtime — the supported default — and reach for Redis scaling when you grow.</p>
    <code>composer require nsd7/surge</code>
  </div>
</div>
