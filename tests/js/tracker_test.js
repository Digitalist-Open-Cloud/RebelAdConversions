import { assertEquals } from "jsr:@std/assert@1";

// Runs tracker.js in a faked browser that was opened with the given query string.
function load(search) {
  const env = { pings: 0, timers: [] };
  const tracker = { ping: () => { env.pings++; } };
  const window = {
    location: { search },
    Matomo: {
      addPlugin: (name, plugin) => { env.plugin = plugin; },
      getAsyncTrackers: () => [tracker],
    },
  };
  const src = Deno.readTextFileSync(new URL("../../tracker.js", import.meta.url));
  new Function("window", "setTimeout", src)(window, (callback) => env.timers.push(callback));
  env.consent = () => window.Matomo.RebelAdConversions.setConsentGiven();
  env.wait = () => { while (env.timers.length) env.timers.shift()(); };
  return env;
}

Deno.test("nothing is sent about a click before the visitor agreed", () => {
  const env = load("?gclid=Abc-123_xyz");

  assertEquals(env.plugin.log(), "");
  assertEquals(env.plugin.goal(), "");
  assertEquals(env.pings, 0);
});

Deno.test("the click is sent with the requests after the visitor agreed", () => {
  const env = load("?utm_source=x&gclid=Abc-123_xyz&other=1");
  env.consent();

  assertEquals(env.plugin.log(), "&rac=gclid:Abc-123_xyz");
  assertEquals(env.plugin.goal(), "&rac=gclid:Abc-123_xyz");
  env.wait();
  assertEquals(env.pings, 0);
});

Deno.test("the click is sent on its own when the visitor agrees after the page view", () => {
  const env = load("?msclkid=0123456789abcdef");
  assertEquals(env.plugin.log(), "");

  env.consent();
  env.wait();

  assertEquals(env.pings, 1);
  assertEquals(env.plugin.ping(), "&rac=msclkid:0123456789abcdef");
});

Deno.test("a page that was not opened from an ad sends nothing", () => {
  for (const search of ["", "?page=2", "?gclid=", "?gclid=a%22b<script>", "?xgclid=Abc-123_xyz"]) {
    const env = load(search);
    env.consent();
    env.wait();

    assertEquals(env.plugin.log(), "");
    assertEquals(env.pings, 0);
  }
});
