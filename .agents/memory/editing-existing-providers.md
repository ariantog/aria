---
name: Never overwrite an existing service provider or config file
description: A near-miss where rewriting AppServiceProvider silently deleted security and domain boot logic.
---

When adding one small thing to an existing framework bootstrap file (a service provider, middleware
stack, config file), **read the current contents and edit in place**. Never write the file from a
mental template of "what a provider looks like".

**Why:** Rewriting `AppServiceProvider` to register a single view composer silently deleted a model
observer, a polymorphic morph map, a superadmin `Gate::before` bypass, an immutable-date default, a
production destructive-command guard, and a production password policy. Nothing failed loudly — the
app still booted and pages still rendered. The damage only surfaced in code review. Worse, the
missing `Gate::before` sent me down a wrong path: I "discovered" superadmins had no permissions and
started granting them explicitly, treating a self-inflicted regression as a pre-existing gap.

**How to apply:** For this class of file, prefer a targeted edit over a full write. If a full
rewrite is unavoidable, diff it against the original before moving on. And when investigation turns
up something surprising about a file you have already touched this session, check your own diff
before concluding it was always that way.
