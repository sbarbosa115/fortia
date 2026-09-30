## 2. Plan the feature before writing code

A plan is short and written down. Put it in the PR description, or in `docs/pdr/prd-<feature>.md` when the feature
is big enough to need a product document. It answers five questions:

1. **Who is it for and what do they do with it?** The role (and its URL space) and the job the feature does for
   them, in one or two sentences. Ask the user only what the code cannot answer.
2. **Where does it live?** On the backend, which bounded context owns the data it changes (§4.1). On the frontend,
   which FSD layer and slice (§4.2). A new screen is usually a tab or section of an existing page, not a new menu
   entry.
3. **Which layers does it touch?** Name them and skip the rest. Most features match one of these shapes:

   | Shape | Backend | Frontend |
   |---|---|---|
   | New thing a user manages | entity → migration → repository → command/handler → Input → Output → controller | entity slice → feature slice(s) → page/widget → route → menu → strings |
   | New field on an existing thing | entity + migration → Input → Output → controller (PATCH) | the form and the table that show it → strings |
   | New action on a row | command/handler → controller route → Output (if the response changes) | a feature slice with the button and its modal → strings |
   | New read-only view | query → Output → controller | widget/page → route → menu → strings |

4. **What is the nearest existing feature?** Open its files next to the ones you write and copy its *shape*: where
   it lives, how it is scoped, how it is tested. Do not copy its age. New code meets today's bar (§5, §6).
5. **How will it be verified?** List the tests you will write first (§4) and the browser cases you will add to
   the regression suite (§8).

Also list what you are deliberately leaving out. It goes in the README's "Known gaps" section at the end.

If the plan spans more than one bounded context or more than one new screen, consider splitting it into items that
can be built in parallel (§2b) before branching.
