const FAQ = [
  { q: "Which plan is right for me?", a: "Start on Free to try the essentials. Move to Business when you need conversion tracking, webhooks, A/B tests or higher volume — upgrading is instant and quotas apply immediately." },
  { q: "What happens when I reach my monthly limits?", a: "Your short links keep redirecting. New link creation pauses at the link quota, and click/conversion tracking pauses at the event quota until the cycle resets or you upgrade." },
  { q: "How long is analytics data kept?", a: "Retention depends on your plan: 30 days on Free, 3 years on Business, 5 years on Advanced and unlimited on Enterprise." },
  { q: "Can I use my own domain?", a: "Yes. Add a domain, point a CNAME record at us, and verify it — then create links like go.yourbrand.com/offer." },
  { q: "Is there an API?", a: "Every feature is available over a REST API with API-key authentication, plus signed webhooks for real-time events." },
];

export function Faq() {
  return (
    <dl className="mx-auto max-w-3xl divide-y divide-border rounded-xl border border-border bg-card">
      {FAQ.map((item) => (
        <details key={item.q} className="group px-5 py-4">
          <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
            <dt>{item.q}</dt>
            <span aria-hidden className="text-muted-foreground transition-transform group-open:rotate-45">+</span>
          </summary>
          <dd className="mt-2 text-sm text-muted-foreground">{item.a}</dd>
        </details>
      ))}
    </dl>
  );
}
