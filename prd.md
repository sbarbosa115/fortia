# PRD — Mappi (reconstrucción agnóstica de Skyline)

| Campo | Valor |
|---|---|
| Producto | **Mappi** (antes "Skyline" / "QuestionAIre") |
| Tipo de documento | PRD de migración con paridad funcional 1:1 |
| Fecha | 2026-09-30 |
| Fuentes | `skyline-backend`, `skyline-ui` (app del respondente), `skyline-admin-ui` (consola del operador) |
| Alcance | Todo lo que existe hoy, descrito de forma independiente de la tecnología |
| Idioma | Español. Los nombres de campos, rutas, enums y códigos de error se dejan tal cual porque forman parte del contrato |

---

## 0. Cómo leer este documento

### 0.1 Principio de agnosticismo

Este PRD describe **qué** debe hacer el sistema, no **con qué** construirlo. Cualquier lenguaje, framework, base de datos, nube o proveedor sirve si cumple los contratos y reglas descritos aquí.

- Los proveedores externos se nombran por **capacidad**: "proveedor de identidad", "pasarela de pagos", "almacenamiento de objetos", "modelo de lenguaje (LLM)", "servicio de correo", "transcripción de voz en tiempo real", "scraper de catálogos", "plataforma de e-commerce".
- La implementación actual de cada capacidad aparece **solo como referencia** en el [Anexo E](#anexo-e--referencia-de-la-implementación-actual). No es un requisito.
- Los **contratos** (rutas HTTP, nombres de campos, enums, códigos de error, formatos de URL públicas) sí son requisito cuando haga falta compatibilidad hacia atrás: enlaces ya compartidos, integraciones externas con API key, receptores de webhooks. Si la migración decide romperlos, debe hacerlo de forma explícita y con un plan de redirección.

### 0.2 Convenciones

- **DEBE**: requisito obligatorio para la paridad.
- **DEBERÍA**: recomendado; puede cambiarse si hay una razón documentada.
- **[DEUDA]**: comportamiento actual que parece un defecto o riesgo. Se documenta para que la migración decida de forma consciente si replicarlo o corregirlo (ver §15).
- **[CLIENTE]**: lógica específica de un cliente que hoy está fija en el código. Recomendación: convertirla en configuración.

---

## 1. Resumen del producto

Mappi es una plataforma **SaaS B2B multi-tenant** para crear cuestionarios ("experiencias"), a menudo con ayuda de IA, enviarlos a respondentes y convertir sus respuestas en resultados accionables: recomendaciones de producto, diagnósticos con puntaje y niveles, perfiles, reportes y dashboards.

### 1.1 Los tres flujos de negocio

1. **Quiz Funnel (e-commerce).** Se importa el catálogo de una tienda (por scraping de la web o sincronizando con la plataforma de e-commerce). La IA genera un cuestionario que se inserta como widget en la tienda. Cuando el visitante termina, la IA le recomienda productos del catálogo.
2. **Mapeo de procesos / Organizaciones.** El cliente registra organizaciones y sus miembros, les **asigna** cuestionarios, sigue el progreso, revisa respuestas pregunta por pregunta, pide correcciones y agrupa las asignaciones en **proyectos** con fecha límite.
3. **Evaluaciones y diagnósticos.** Un enlace público recoge respuestas. Un diagnóstico puntúa las respuestas por categorías, ubica al respondente en un nivel (tier) y le entrega recomendaciones, plan de acción y un PDF. Las **cadenas de prompts** permiten que un LLM genere la siguiente etapa del cuestionario a partir de las respuestas de la anterior.

### 1.2 Las tres piezas del sistema

| Pieza | Usuarios | Función |
|---|---|---|
| **API / Backend** | Las dos apps, integradores externos, pasarela de pagos, plataforma de e-commerce | Dueño de todos los datos y reglas. Trabajos asíncronos, tareas programadas, webhooks |
| **App del respondente** | Visitantes anónimos, miembros de organizaciones | Responder cuestionarios y ver resultados |
| **Consola del operador** | Usuarios del cliente (tenant) y super-admins | Crear, publicar, asignar, revisar, analizar, configurar marca, facturación e integraciones |

Existe además una consola interna de super-admin ("tower") fuera del alcance de este PRD. Solo se documentan los endpoints `/admin/*` que consume.

---

## 2. Objetivos y no objetivos

### 2.1 Objetivos

1. Reconstruir el producto en cualquier stack **sin perder funcionalidad** (paridad 1:1).
2. Mantener la compatibilidad de los **enlaces públicos ya distribuidos** (`/q/{id}`, `/f/{id|slug}`, `/a/{id}`, `/session/{id}/results`) y de la **API externa** y los **webhooks**.
3. Migrar los datos existentes sin pérdida: cuentas, cuestionarios, flujos, sesiones, resultados, organizaciones, asignaciones, proyectos, planes, productos, estilos, API keys, webhooks, archivos.
4. Dejar documentadas las decisiones sobre deuda técnica (§15).

### 2.2 No objetivos

- Rediseñar el producto o añadir funciones nuevas (se pueden proponer por separado).
- Reconstruir la consola "tower" de super-admin.
- Reconstruir el servicio interno de analítica/uso. Se trata como dependencia externa con el contrato de §13.8. La migración puede decidir absorberlo.

---

## 3. Glosario

| Término | Definición |
|---|---|
| **Cuenta / Cliente / Tenant** | La empresa que paga. Se identifica con `customer_id` (8 caracteres alfanuméricos aleatorios) |
| **Usuario** | Persona que entra a la consola. Pertenece a una sola cuenta |
| **Root / Owner** | El usuario que creó la cuenta |
| **Respondente** | Quien responde un cuestionario. Anónimo o miembro de una organización |
| **Cuestionario / Experiencia** | Conjunto ordenado de preguntas con un comportamiento al terminar |
| **Pregunta** | Unidad de pantalla del cuestionario. Tiene uno o varios controles, aunque en la práctica se usa el primero |
| **Control (InputControl)** | El campo de respuesta de una pregunta (radio, checkbox, texto, audio, archivo…) |
| **Flujo (Flow)** | Máquina de estados que envuelve al cuestionario: define la entrada, las etapas encadenadas y el resultado. Tiene `slug` público |
| **Estado (State)** | Nodo del flujo: `questionnaire`, `prompt`, `diagnostic`, `quiz_funnel`, `result`, `regular` |
| **Cadena (Chain)** | Flujo con estados `prompt`: cada etapa la genera un LLM a partir de las respuestas anteriores |
| **Sesión** | Una respuesta en curso o terminada. Es una copia del cuestionario con los valores del respondente |
| **Resultado de sesión** | Lo que se calcula al terminar: productos, diagnóstico, perfil |
| **Diagnóstico** | Configuración de puntaje: tiers (niveles), recomendaciones y plan de acción por tier |
| **Tier** | Banda de puntaje `[min, max]` con nombre y descripción |
| **Organización** | Grupo de personas (miembros) de un cliente al que se le asignan cuestionarios |
| **Miembro (OrganizationUser)** | Persona dentro de una organización. Tiene nombre, email y/o teléfono, rol y área |
| **Asignación (Assignation)** | Envío de un cuestionario a una organización, con audiencia y tipo (`default` o `follow_up`) |
| **Follow-up** | Asignación de sesión **compartida**: todos los miembros aportan a la misma sesión, con recordatorios diarios, revisión y reintentos |
| **Intento (Attempt)** | Cada ronda de un follow-up. Un reintento ("enviar a corrección") crea el intento n+1 |
| **Proyecto** | Agrupación de asignaciones follow-up de una misma organización, con fecha límite |
| **Plan / Feature** | Catálogo comercial. Un plan asigna límites a cada feature |
| **Job** | Trabajo asíncrono consultable por polling |
| **Estilos (Styles)** | Marca visual de la cuenta aplicada a la app del respondente |

---

## 4. Actores, roles y multi-tenancy

### 4.1 Actores

| Actor | Autenticación | Accede a |
|---|---|---|
| Visitante / respondente anónimo | Ninguna | App del respondente en rutas públicas |
| Miembro de organización (respondente de asignación) | "Login" de identidad contra la lista de miembros; recibe un token de sesión de respondente | `/a/{id}` |
| Usuario de consola `Customer-Admin` | Email + contraseña o Google | Toda la consola, lectura y escritura |
| Usuario de consola `Customer-Read-Only` | Igual | Consola solo lectura |
| Owner (`root = true`) | Igual | Igual que `Customer-Admin` |
| Super-admin `Admin` | Igual (asignado a mano en el proveedor de identidad) | Todas las cuentas; endpoints `/admin/*`; "asumir" cualquier cuenta |
| Integrador externo | Header `X-API-Key` | `/external/*` |
| Pasarela de pagos | Firma en header | Webhook de pagos |
| Plataforma de e-commerce | HMAC en header | Webhooks de cumplimiento (GDPR) |
| Receptor de webhooks del cliente | Verifica la firma HMAC que enviamos | Recibe `questionnaire.completed` |

### 4.2 Roles

| Rol | Significado | Cómo se otorga |
|---|---|---|
| `Admin` | Super-usuario de toda la plataforma | Solo a mano en el proveedor de identidad |
| `Customer-Admin` | Administrador de la cuenta | Automático al registrarse (root). Asignable con `POST /users` |
| `Customer-Read-Only` | Miembro de solo lectura | Asignable con `POST /users` |

- Atributo de usuario `root` (`"true"`/`"false"`) marca al dueño de la cuenta.
- `ADMIN_GROUPS = {Admin, Customer-Admin}`: los grupos que pueden crear usuarios y usar endpoints de escritura "admin".
- `ASSIGNABLE_ROLES = {Customer-Admin, Customer-Read-Only}`.
- **Permiso de escritura en la consola** = `root` **o** pertenece a `Admin` o `Customer-Admin`. Las comprobaciones usan la lista completa de grupos, nunca el rol mostrado.
- **Rol mostrado** (precedencia): `Admin > Customer-Admin > Customer-Read-Only`.

**Matriz de permisos mostrada al crear un usuario:**

| Acción | Admin | Solo lectura |
|---|---|---|
| Ver cuestionarios | ✓ | ✓ |
| Crear y editar cuestionarios | ✓ | – |
| Borrar cuestionarios | ✓ | – |
| Ver respuestas | ✓ | ✓ |
| Exportar reportes | ✓ | ✓ |
| Invitar usuarios | ✓ | – |
| Editar facturación y workspace | ✓ | – |

### 4.3 Multi-tenancy

- El tenant es la **cuenta** (`customer_id`). Toda entidad del cliente DEBE llevar `customer_id`.
- La identidad del usuario (nombre, email) vive en el proveedor de identidad, no en la fila de la cuenta.
- La lectura y escritura DEBEN filtrarse por el `customer_id` del llamante, salvo para `Admin`, que ve todas las cuentas en los listados (cuestionarios, organizaciones, asignaciones).
- Los emails de usuarios de consola son **únicos en todo el sistema**.

### 4.4 Suplantación ("asumir cliente")

- Un `Admin` puede enviar el header `X-Assume-Customer-Id: <customer_id>` (sin distinguir mayúsculas en el nombre).
- La petición se ejecuta **como el usuario root de esa cuenta**: grupos `[Customer-Admin]`, `root = true`, email y nombre del root. No hay bypass de admin mientras se asume, y aplican los límites del plan del cliente asumido.
- Un llamante que no es `Admin` y envía el header recibe `403 ASSUME_NOT_ALLOWED`. Una cuenta inexistente da `404 ASSUMED_CUSTOMER_NOT_FOUND`.
- Los endpoints `/admin/*` ignoran el header y usan el llamante real.
- [DEUDA] No se audita. Se recomienda registrar quién asumió a quién y cuándo.

---

## 5. Arquitectura lógica (agnóstica)

```
                 ┌────────────────────┐       ┌──────────────────────┐
 Respondentes ──►│ App del respondente│       │ Consola del operador │◄── Usuarios de cuenta / Admin
                 └─────────┬──────────┘       └──────────┬───────────┘
                           │   HTTP JSON (API /api/v1)   │
                           ▼                             ▼
                 ┌───────────────────────────────────────────────────┐
 Integradores ──►│                       API                         │◄── Webhooks entrantes
 (X-API-Key)     │  auth · validación · gate de plan · casos de uso  │    (pagos, e-commerce)
                 └──┬──────────┬──────────┬──────────┬──────────┬────┘
                    │          │          │          │          │
             Base de datos  Cola de    Almacén de  Bus de     Servicio de
             (persistencia)  jobs      objetos     eventos    uso/analítica
                    │          │                     │
                    │     Workers asíncronos    Despachador de
                    │    (IA, estilos, scraping) webhooks salientes
                    │
              Tarea programada diaria (recordatorios)

 Dependencias externas por capacidad: proveedor de identidad (+ login con Google),
 LLM, transcripción en tiempo real, correo transaccional, pasarela de pagos,
 plataforma de e-commerce, scraper de catálogos/perfiles, navegador headless,
 seguimiento de errores, píxeles de marketing.
```

**Requisitos de arquitectura:**

- **A1.** La API DEBE poder responder toda petición síncrona en ≤ 29 s. Todo lo que tarde más (generación por IA, scraping, estilos, evaluación de respuestas, recomendación de productos, chat) DEBE ejecutarse como **job asíncrono** consultable con `GET /jobs/{job_id}`.
- **A2.** El orden de procesamiento de cada petición DEBE ser: **autenticación → validación de entrada → gate de plan → ejecución**.
- **A3.** La consola no tiene datos propios: todo pasa por la API.
- **A4.** No hay canal en tiempo real (websocket) entre la API y las apps. El progreso asíncrono se consulta por polling.
- **A5.** Los eventos de uso y analítica se emiten de forma asíncrona y no deben bloquear la respuesta al usuario.

---

## 6. Modelo de datos

Reglas generales:

- Las fechas y horas se guardan en ISO-8601 UTC (`Z`). Las fechas de calendario van como `YYYY-MM-DD`.
- Los IDs son UUIDv4 salvo que se indique otro formato.
- **No hay borrado lógico** salvo en dos casos: las API keys se revocan (`status = revoked`) y los cuestionarios se desactivan (`is_active = false`). Todo lo demás se borra físicamente.
- La elección de base de datos es libre. Los "índices" listados son **patrones de acceso** que la implementación DEBE soportar con eficiencia.

### 6.1 Customer (cuenta)

| Campo | Tipo | Notas |
|---|---|---|
| `customer_id` | string(8) | PK, alfanumérico aleatorio |
| `created_at` | datetime | |
| `language` | enum `es-CO` \| `en-US` | Default `es-CO` |
| `source` | string | Origen del registro, default `"default"`. Acceso por `source` + `created_at` |
| `settings` | objeto `CustomerSettings` | Ver abajo |
| `plan` | objeto `CustomerPlan` \| null | Embebido |
| `shopify_shop`, `shopify_token`, `shopify_refresh_token` | string \| null | Conexión con la plataforma de e-commerce. Los tokens son secretos |
| `onboarding_completed` | bool \| null | null = cuenta antigua (se deriva, §7.15) |
| `stripe_trial_used_at` | datetime \| null | Marca de "ya usó su prueba en la pasarela". Se escribe una vez y nunca se borra |

**CustomerSettings:**

| Campo | Tipo | Regla |
|---|---|---|
| `language` | `es-CO` \| `en-US` | Nunca null |
| `transcription_url` | string \| null | URL alternativa del servicio de transcripción |
| `pixel_id` | string ≤ 64 \| null | Píxel de Meta |
| `linkedin_partner_id`, `linkedin_conversion_id` | string ≤ 64 \| null | |
| `google_ads_id`, `google_ads_conversion_label` | string ≤ 64 \| null | |
| `max_files` | int 1–20 | Default 10. Máximo de archivos por pregunta de tipo archivo |

**CustomerPlan (embebido):**

| Campo | Tipo |
|---|---|
| `plan_id` | string |
| `from_at`, `to_at` | date (vigencia, inclusiva) |
| `billing_interval` | `month` \| `year` (default `month`) |
| `stripe_customer_id`, `stripe_subscription_id` | string \| null (IDs en la pasarela de pagos) |
| `trial_end` | datetime \| null |
| `discount` | `{coupon_id, promotion_code?, percent_off?, amount_off?, currency?, duration, ends_at?}` \| null |
| `created_at`, `updated_at` | datetime |

### 6.2 User (vive en el proveedor de identidad)

| Campo | Notas |
|---|---|
| `email` | Único global; es el nombre de usuario; verificado automáticamente |
| `name` | 1–50 |
| `customer_id` | ≤ 16 |
| `root` | `"true"` / `"false"` |
| grupos | `Admin`, `Customer-Admin`, `Customer-Read-Only` |

### 6.3 Feature (catálogo global)

- `id` = slug del nombre; no cambia nunca.
- `feature_name` (1–100), `feature_description` (≤ 1000, default `""`), timestamps.
- **Slugs canónicos:** `regular`, `diagnostic`, `quiz-funnel`, `chain`, `chat`, `organizations`, `assignations`, `styles`, `analytics`, `dashboards`, `users`, `api`, `webhook`, `profile`, `responses`.

### 6.4 Plan (catálogo global)

| Campo | Tipo | Regla |
|---|---|---|
| `id` | string | Slug del nombre |
| `plan_name` | 1–100 | Único |
| `plan_description` | ≤ 1000 | Default `""` |
| `features` | `[{feature_id, limit:int}]` | IDs únicos. **limit < 0 = ilimitado; 0 = no incluido** |
| `max_questionnaires` | int \| null | null = sin tope; negativo = ilimitado |
| `max_responses` | int \| null | Igual |
| `price_amount` | int ≥ 0 \| null | En unidades menores (centavos). null = "precio a consultar" |
| `currency` | 3 letras | Default `usd` |
| `stripe_price_id` | ≤ 255 \| null | ID del precio mensual en la pasarela |
| `yearly_price_amount`, `stripe_yearly_price_id` | | Precio anual |
| `trial_days` | 0–365 | Default 0 |

DEBE existir un plan con id `starter` (plan de prueba al registrarse).

### 6.5 Questionnaire

| Campo | Tipo | Notas |
|---|---|---|
| `questionnaire_id` | UUIDv4 | PK |
| `customer_id` | string | Acceso por cuenta |
| `title` | string | Requerido |
| `description`, `disclaimer` | string \| null | |
| `capture_user_data` | bool | Default false. Pide nombre, email y teléfono al final |
| `landing_page` | bool | Default false. Muestra una portada antes de empezar |
| `type` | enum | `default`, `ecommerce`, `quiz_funnel`, `samurai8`, `ai_team_profile`, `diagnostic`, `prompt` |
| `is_active` | bool | Default true |
| `on_completed` | `OnCompleted` \| null | Qué pasa al terminar |
| `parent` | `"ROOT"` \| questionnaire_id | Las etapas generadas de una cadena apuntan a su raíz. Acceso por `parent` |
| `origin_session_id` | UUID \| null | Sesión que originó la etapa generada. Acceso por este campo |
| `session_id`, `started_at`, `ended_at` | null | Siempre null en el cuestionario guardado; se llenan en la sesión |
| `question_count`, `is_chain`, `slug` | derivados | Copias desnormalizadas para listados |
| `questions` | `Question[]` | |
| `created_at`, `updated_at` | datetime | |

**Question:**

| Campo | Tipo | Notas |
|---|---|---|
| `id` | UUID | |
| `order` | int | Base 0 |
| `title` | string | |
| `description`, `disclaimer` | string \| null | |
| `theme_name` | enum \| null | `gender`, `quote`, `weight`, `height`, `celebration`, `user-capture-data`, `jeans-size`, `weight-composite`, `organization-users-login` |
| `statements` | any \| null | |
| `visibility` | string[] | Géneros para los que se muestra (`male`, `female`) |
| `options` | `InputControl[]` | Se usa el primer control que se pueda renderizar |
| `acceptance_criteria` | string[] ≤ 10 | Criterios para la evaluación por IA |
| `max_followups` | int 0–5 \| null | Reintentos permitidos cuando la IA rechaza la respuesta |
| `attachment_required` | bool \| null | |
| `category` | string \| null | Categoría de puntaje del diagnóstico |
| `required` | bool | Default true |
| *Solo en sesión:* `improvement_message`, `flagged_answer`, `review` | | Ver §6.10 |

**InputControl:**

| Campo | Tipo | Notas |
|---|---|---|
| `name` | string (UUID) | |
| `type` | enum | `radio`, `checkbox`, `select`, `range`, `text`, `audio`, `ranking`, `file`, `message`, `email`, `tel`, `phone` |
| `options` | `[{label, value?, visibility[]}]` | Si `value` es null, se usa `label` |
| `validations` | `[{type, value?, message?, pattern?}]` | `type`: `min`, `max`, `required`, `format` |
| `default_value` | any | Placeholder o valor inicial del slider |
| *Solo en sesión:* `value` | string \| string[] | Seleccionados, transcripciones o claves de archivo |
| *Solo en sesión:* `timestamp`, `skipped` (default false), `locked` | | `locked` se usa en reintentos |

**OnCompleted** (unión discriminada por `type`):

- `default {message?}`
- `quiz_funnel {products[]}`
- `diagnostic {tiers[], recommendations[], action_plan[]}`
  - Tier: `{id, name, description?, min, max, visible = true}`
  - Recomendación: `{tier_id, recommendation, visible}`
  - Acción: `{tier_id, action, visible}`
- `process_mapping {message?}`

### 6.6 Flow

| Campo | Tipo | Regla |
|---|---|---|
| `id` | string(20) | ID corto |
| `slug` | string 1–100 | `^[a-z0-9]+(-[a-z0-9]+)*$`, **único en todo el sistema** |
| `detail` | string | |
| `customer_id` | string | |
| `questionnaire_id` | UUID | **Un flujo por cuestionario** |
| `source_url` | string \| null | Origen de la tienda (quiz funnel). Acceso por este campo |
| `states` | `State[]` | Ver abajo |
| `cta` | objeto \| null | `{title 1–120, description ≤ 200, button:{text 1–50, url 1–2048 que empieza con http(s)://}}` |
| `layout` | string[] \| null | Cada uno de `score`, `tier`, `categories`, `recommendations`, `action_plan`, `pdf`, `cta` como máximo una vez |
| `result_copy` | objeto \| null | 15 textos opcionales ≤ 300 caracteres (se recortan; vacío = texto por defecto): `eyebrow, title, subtitle, tier_label, overall_score, categories_title, categories_subtitle, chart_title, chart_subtitle, chart_legend, recommendations, action_plan, report_title, report_subtitle, download` |
| `created_at`, `updated_at` | datetime | |

**State:** `{state_id (15 caracteres, único en el flujo), type, parameters{}, outputs{}, next?}`.

- `type`: `questionnaire`, `regular`, `quiz_funnel`, `diagnostic`, `prompt`, `result`.
- `parameters.questionnaire_id` en los estados `questionnaire`.
- Los estados `prompt` guardan la referencia al texto del prompt en el almacén de objetos.

**Tipo mostrado del flujo:** el primer estado especial presente, en este orden de prioridad: `prompt` → `diagnostic` → `quiz_funnel` → si no hay ninguno, `default`.

### 6.7 Diagnostic

`{id, questionnaire_id, tiers, recommendations, action_plan}`. Acceso por `questionnaire_id`.

### 6.8 Prompt

`{id, questionnaire_id, customer_id, s3_path (referencia al texto en el almacén de objetos), outcome?, order = 0}`. Acceso por `questionnaire_id`.

### 6.9 Session (respuesta)

Copia completa del cuestionario más estos campos:

| Campo | Notas |
|---|---|
| `session_id` | UUIDv4, PK. Acceso por `questionnaire_id` |
| `started_at`, `ended_at` | |
| `flow_id` | |
| `status` | Máquina de estados: `filling` → `filled_out` → `processing` → `completed`. Valores antiguos: `in_progress` (= filling), `submitted` (= filled_out) |
| `user_data` | `{name, email, phone}` si se capturó |
| `assignations_id`, `organization_user_id` | Solo en sesiones de asignación |
| `assignation_type` | `follow_up` \| null |
| `attempt` | int, default 1 |
| Por pregunta: `review` | `{status: approved \| rejected, comment?, reviewed_at, attempt}` |
| Por pregunta: `improvement_message`, `flagged_answer` | Resultado de la evaluación por IA |

### 6.10 SessionResults

`{session_id (PK), products?, ai_team_profile?, diagnostic?}`.

**DiagnosticResult:**

```
{ type: "diagnostic",
  score: {value, max},
  categories: [{id, name, score, max}],
  tiers: [...solo los visibles],
  recommendations: [{tier_id, recommendation}],
  action_plan: [{tier_id, action}] }
```

### 6.11 Product

`{product_id, customer_id, name, description (HTML), price (decimal, se interpreta con tolerancia desde texto), image_url?, product_url?, source_url?, questionnaire_id?, created_at, updated_at}`.

Acceso por `customer_id`, `source_url` y `questionnaire_id`.

### 6.12 Organization

`{organization_id (UUIDv4), customer_id, name (1–120), domain_email? (único en todo el sistema, en minúsculas), description? (≤ 1000), active = true, timestamps}`.

Acceso por `domain_email` y por `customer_id`.

### 6.13 OrganizationUser (miembro)

| Campo | Regla |
|---|---|
| `organization_user_id` | UUIDv4 |
| `organization_id` | Acceso por este campo |
| `name` | 1–200. **Normalizado:** minúsculas, sin acentos, espacios simples |
| `email` | Minúsculas. Acceso por email |
| `phone` | Solo dígitos con `+` inicial opcional, ≤ 50. Acceso por phone |
| `role`, `area` | Texto libre ≤ 120 |
| timestamps | |

Cada miembro necesita al menos email o teléfono. El email es único dentro de la organización.

### 6.14 Assignation

| Campo | Regla |
|---|---|
| `assignations_id` | UUIDv4 |
| `customer_id`, `organization_id`, `questionnaire_id` | Acceso por `questionnaire_id`, `customer_id` y `project_id` |
| `name` | 1–200 |
| `description` | ≤ 2000. Nota interna; el respondente nunca la ve |
| `max_follow_ups` | ≥ 0 (la consola envía 2) |
| `active` | Default true |
| `type` | `default` \| `follow_up`. **Inmutable** |
| `due_date` | `YYYY-MM-DD`, solo en follow-up |
| `audience` | `{type: all \| members \| area \| role, values[] ≤ 500}`. `all` sin valores; los demás con al menos uno. `members` = UUIDs de miembros. `area` y `role` se comparan sin distinguir mayúsculas ni acentos |
| `questions` | ≥ 1. Es la **diapositiva de registro** (login del respondente), no el cuestionario |
| *Gestionados por el servidor:* `project_id?`, `shared_session_id?`, `attempts[{number ≥ 1, session_id, created_at}]`, `last_reminder_sent_at?` | |
| timestamps | |

**Regla:** un cuestionario se puede asignar a **una sola organización**.

### 6.15 AssignationAnswer

Clave `(assignations_id, organization_user_id)` → `{session_id}`. Se escribe cuando el miembro envía la primera etapa.

### 6.16 Project

`{project_id, customer_id, organization_id (inmutable), name (1–200), description? (≤ 2000), due_date (requerido al crear; puede ser null en filas antiguas), timestamps}`.

Una asignación pertenece como máximo a un proyecto.

### 6.17 Job

| Campo | Valores |
|---|---|
| `job_id` | `"job_"` + ID ordenable en el tiempo (tipo ULID) |
| `job_type` | `styles`, `profile_customization` (antiguo), `answer_evaluation`, `linkedin_questionnaire`, `prompt_questionnaire`, `create_quiz_funnel`, `scrape_products`, `process_completed_session`, `chat`, `chat-questionnaire-created`, `chat-questionnaire-drafted`, `chat-questionnaire-approved` |
| `status` | `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `CANCELLED` |
| `payload` | Interno. **Nunca se devuelve** |
| `result` | Si falla: `{error:{type, message}}` |
| `stage` | Sub-etapa de progreso (p. ej. estilos: `reading_website` → `designing_styles` → `saving`) |
| timestamps | |

Acceso por `job_type` y por `status`.

### 6.18 Styles

Clave `customer_id` → `{website?, styles}`. `styles` (camelCase):

```
logoUrl
font {family, url}
body {background, color}
h1 | h2 | h3 | p {fontSize, fontWeight, color}
label {fontSize, fontWeight, color, textTransform, letterSpacing}
a {color}
button.primary | button.secondary {background, backgroundHover, color, border, padding, fontSize, fontWeight, borderRadius}
input {background, color, placeholderColor, border, borderFocus, borderRadius, padding, fontSize}
```

Hay un conjunto de **estilos por defecto** definido por la plataforma.

### 6.19 QuestionnaireDashboard

`{questionnaire_id (PK), customer_id, type, charts[{id, chart_type, title, question_ids[], order}], created_at}`. Se crea una sola vez.

- `type`: `satisfaction`, `knowledge`, `profiling`, `recommendations`, `eligibility`, `opinion`.
- `chart_type`: `kpi`, `gauge`, `line`, `donut`, `bar`, `horizontal_bar`, `stacked_bar`, `treemap`, `histogram`, `boxplot`, `ranking_avg`, `heatmap`, `tier_distribution`.

### 6.20 ApiKey

`{id = SHA-256 de la clave, customer_id, name (1–100), status: active | revoked, created_at, expires_at?, last_used_at?}`. **La clave en claro nunca se guarda.**

### 6.21 Webhook

`{id (UUID), customer_id, url (solo https), event_type: questionnaire.completed, method: POST, timestamps}`.

### 6.22 Video (documentación)

`{id (UUID), title (1–200), description (≤ 2000), url (1–500, YouTube), language: es | en, category (≤ 100), order ≥ 0, duration_minutes ≥ 0, timestamps}`.

### 6.23 AppSetting

`{key, value}`. Ejemplo: modelo de LLM por defecto para generar cuestionarios.

### 6.24 SystemPrompt

Texto Markdown **versionado** por clave (12 claves, ver [Anexo D](#anexo-d--claves-de-system-prompts)). El historial de versiones es el historial de ediciones: `{key, text, version_id, updated_at, updated_by}`.

### 6.25 Datos que NO se guardan localmente

- **Cupones y códigos promocionales:** viven solo en la pasarela de pagos.
- **Pagos:** viven en la pasarela y en el servicio de uso/analítica.
- **Contadores de uso:** viven en el servicio de uso/analítica (§13.8).
- Hay tablas antiguas (`session`, `questionnaire-analytics`) que la migración puede descartar tras verificar que no tienen datos vivos.

---

## 7. Reglas de negocio

### 7.1 Gate de plan

**Plan aplicable.** La cuenta DEBE tener un plan, la fila del plan DEBE existir y la fecha de hoy (UTC) DEBE estar en `from_at..to_at` inclusive. Si no, se rechaza con:

| Condición | Razón |
|---|---|
| La cuenta no tiene plan | `NO_PLAN` |
| Hoy está fuera de la vigencia | `PLAN_INACTIVE` |
| El plan no existe en el catálogo | `PLAN_NOT_FOUND` |

**Límites:**

- Feature ausente del plan → `FEATURE_NOT_IN_PLAN`. `limit < 0` → ilimitado. `limit ≥ 0` → se rechaza cuando `used >= limit` (`FEATURE_LIMIT_REACHED`).
- `max_questionnaires` limita la **suma** de los contadores `regular + diagnostic + quiz-funnel + chain + chat` → `QUESTIONNAIRE_LIMIT_REACHED`.
- `responses` se limita con `max_responses`, no con la lista de features → `RESPONSE_LIMIT_REACHED`.
- Respuesta de rechazo: `429 PLAN_LIMIT_REACHED` con `details: {reason, feature}`.
- Si el servicio de uso no responde: `503 USAGE_UNAVAILABLE`.

**Dos tipos de gate:**

| Tipo | Qué comprueba | Consume cuota | Puede dar 503 |
|---|---|---|---|
| **Capacidad** | Límite contra contadores de uso | Sí (la acción cuenta) | Sí |
| **Feature** | Solo que el plan incluye la feature (límite presente y ≠ 0) | No | No |

- `Admin` siempre pasa el gate.
- **No se aplica gate a:** endpoints admin, borrados, etapas hijas de una cadena, generación desde LinkedIn, checkout, onboarding, cambio de idioma de la cuenta.
- Los contadores son **por periodo mensual** (ventana del plan).

### 7.2 Qué cuenta como uso

| Acción | Feature que suma |
|---|---|
| Crear cuestionario (POST, copia, quiz funnel, chat, LinkedIn) | Según su tipo: `regular`, `diagnostic`, `quiz-funnel`, `chain`, `chat`. LinkedIn cuenta como `diagnostic` |
| Completar la **última etapa** de una cadena (o un cuestionario simple) | `responses` (una por respondente) |
| Job de estilos completado (si falla, no cuenta) | `styles` |
| Consultar analítica o datos del dashboard | `analytics` |
| Generar un dashboard (una vez por cuestionario) | `dashboards` |
| Cada llamada a la API externa | `api` |
| Cada entrega exitosa de webhook | `webhook` |
| Editar settings de la cuenta (salvo idioma) | `profile` |
| Crear un usuario de equipo | `users` |
| Crear o borrar una organización | `organizations` |
| Crear o borrar una asignación | `assignations` |

### 7.3 Registro y prueba gratuita

- **Registro nativo o primer login con Google:** crea la cuenta con un `customer_id` nuevo, el usuario root en `Customer-Admin`, `onboarding_completed = false` y el plan `starter` desde hoy hasta hoy + 1 mes calendario (el día se ajusta a la longitud del mes).
- **Prueba en la pasarela de pagos:** una sola vez por cuenta, y solo si el plan tiene `trial_days > 0`. Siempre se pide tarjeta. Si no hay método de pago al terminar la prueba, se cancela la suscripción. La primera vez que una suscripción entra en prueba se marca `stripe_trial_used_at`.

### 7.4 Facturación

**Checkout:**

- Es una suscripción con una sola línea en la página de pago alojada por la pasarela.
- Metadatos: `{customer_id, plan_id, billing_interval}`.
- Reutiliza el cliente de la pasarela si ya existe. Siempre admite códigos promocionales.
- URLs de retorno: `{ADMIN_URL}/profile/plans?checkout=success|cancel`.

**Clasificación de un cambio de plan** (se compara contra el plan que la pasarela realmente cobra hoy). Se aplica la primera regla que coincida:

1. El destino no tiene precio → **downgrade**.
2. De anual a mensual → siempre **downgrade**.
3. El plan actual no tiene precio, o el destino cuesta más → **upgrade**.
4. El destino cuesta menos → **downgrade**.
5. Mismo precio, de mensual a anual → **upgrade**. Cualquier otro caso de mismo precio → **downgrade**.

**Efecto:**

- **Upgrade:** cambio inmediato del precio; se cobra al momento la diferencia prorrateada. Si ese cobro falla, el cambio falla.
- **Downgrade:** se programa el plan más barato como siguiente fase por un ciclo. Luego se libera la programación y la suscripción se renueva en el plan nuevo.
- Upgrade y cancelación liberan antes cualquier programación pendiente.
- **Cancelar** = cancelar al final del periodo. **Reanudar** = quitar esa cancelación (idempotente).
- **Revertir** = anular un downgrade programado.

**Eventos del webhook de pagos** (todos idempotentes):

| Evento | Efecto |
|---|---|
| `checkout.session.completed` | Asignar el plan con el periodo de la suscripción; evento `SubscriptionCreated` |
| `invoice.paid` | Reasignar el periodo; registrar el pago en analítica (deduplicado por id de evento; si falla, responder 500 para que la pasarela reintente). Si `billing_reason = subscription_cycle`, evento `SubscriptionRenewed` |
| `customer.subscription.updated` | Reasignar. Si cambió el plan: evento `PlanChanged`; si el plan nuevo es más caro, **reiniciar a 0 todos los contadores de uso** |
| `customer.subscription.deleted` | Evento `SubscriptionCancelled`; borrar el id de suscripción guardado. La ventana pagada expira sola |
| `customer.subscription.trial_will_end` | Evento `TrialWillEnd` |
| `invoice.payment_failed` | Evento `PaymentFailed` |

**Cupones:**

- La pasarela solo restringe cupones por producto, así que los intervalos de facturación se controlan por producto. Un plan cuyo precio mensual y anual comparten producto no admite un cupón limitado a un solo intervalo (`COUPON_INTERVAL_NEEDS_OWN_PRODUCT`).
- Sin `plan_ids`, el cupón aplica a todos los planes comprables.
- Si falla la creación del código promocional, se borra el cupón.

### 7.5 Creación y edición de cuestionarios y flujos

**Validación del flujo:**

- Exactamente **un** estado `questionnaire`.
- `state_id` únicos; `next` debe apuntar a un estado existente.
- Como máximo **10** estados `prompt`.
- Un estado `diagnostic` necesita tiers, salvo que sea el último estado de una cadena de prompts (sin `next`). En ese caso el LLM genera los tiers.

**El diagnóstico DEBE ser puntuable:**

- al menos un tier; ids de tier únicos; `min ≤ max`;
- recomendaciones y acciones referencian tiers existentes;
- puntaje máximo > 0;
- los tiers ordenados empiezan en 0, terminan exactamente en el máximo y son **contiguos**: `siguiente.min = anterior.max + 1`.

**Puntaje máximo de una pregunta:** suma sobre sus controles. Un control `checkbox` o `ranking` aporta la **suma** de los valores de sus opciones; cualquier otro control aporta el **mayor** valor.

**Valores de opción duplicados** dentro de un control se corrigen solos: un número repetido se sube por encima del mayor en uso; un texto repetido recibe sufijo `-2`, `-3`…

**Bloqueo por respuestas:**

- Un cuestionario con alguna respuesta (valor o skip en cualquier sesión) **no se puede editar**: `409 QUESTIONNAIRE_ALREADY_ANSWERED`. La UI ofrece crear una copia.
- Al editar, se reconstruyen el diagnóstico y los prompts y el flujo conserva su id.

**Otras reglas:**

- El `outcome` de un prompt es el siguiente estado terminal del flujo (`diagnostic`, `quiz_funnel` o `result`).
- **Copiar:**
  - Título `"(copia) X"`; si ya existe, `"(copia - N) X"`.
  - Slug `<base>-copia[-N]`.
  - Se copian el diagnóstico y los prompts.
  - [DEUDA] Los prefijos están fijos en español.

### 7.6 Limpieza de la salida del LLM (siempre al generar preguntas)

1. Conservar solo el primer control de cada pregunta.
2. Eliminar preguntas sin control.
3. Vaciar los campos de ejecución que el LLM haya llenado.
4. Renumerar `order` desde 0.
5. Deduplicar los valores de opción (§7.5).
6. Asignar un UUID nuevo a cada pregunta y a cada nombre de control.
7. Si no queda ninguna pregunta, fallar.

### 7.7 Procesamiento al enviar una sesión

1. Marcar `ended_at` y `status = filled_out`.
2. Calcular el resultado según el tipo:
   - **Diagnóstico:**
     - Solo cuentan las preguntas con `category` y máximo > 0.
     - Puntaje de categoría = suma de los valores seleccionados. Total = suma de categorías, redondeo *half-up*.
     - Tier = la banda que contiene el total.
     - Un diagnóstico al final de una cadena puntúa **todas las etapas juntas**, con los ids de pregunta prefijados por etapa.
   - **E-commerce / quiz funnel** (asíncrono, devuelve job):
     - El LLM elige ids de producto del catálogo guardado. Se busca primero por cuestionario y luego por cuenta.
     - Catálogo vacío → sin productos y sin llamar al LLM.
     - Se guardan los resultados.
   - **AI Team Profile:**
     - Puntaje fijo sobre 8 preguntas radio mapeadas a 5 dimensiones (D1–D5).
     - 5 etapas, de "No usage" a "Transformation".
     - Radar, percentiles de referencia y estructura de reporte fija.
   - **Samurai8** [CLIENTE] (un questionnaire_id fijo o el cliente `mateo`):
     - 5 dimensiones; una dimensión de 3 preguntas se escala `round(suma × 6 / 9)`.
     - Tiers: 0–5 Explorador, 6–10 Practicante, 11–15 Estratega, 16–20 Arquitecto, 21–25 Constructor, 26–30 Maestro.
     - Devuelve fortalezas (dimensiones ≥ 4), dimensión más débil, quick wins, roadmap y CTA.
   - **Livingood** [CLIENTE] (cliente `livingood`): cuatro puntajes sobre 100 (Fat Loss, Gut Health, Hormone Balance, Energy & Vitality), más perfil y plan de acción.
   - **Default:** `{type: "default"}`.
3. Luego, **en todos los casos**:
   - `status = completed`;
   - publicar el evento de webhook `questionnaire.completed`;
   - emitir el evento `QuestionnaireSessionCompleted`;
   - contar 1 respuesta (solo en la etapa final de una cadena).
4. La respuesta incluye el `cta`, `layout` y `result_copy` del flujo cuando existen.

### 7.8 Cadenas de prompts

- Cada etapa se genera a partir de: las respuestas de la etapa anterior, el prompt del dueño de la cuenta (**tratado como dato no confiable**) y las reglas de la plataforma.
- Archivos adjuntos a respuestas: hasta **5** por etapa y **32 MB** en total.

  | Tipo | Límite |
  |---|---|
  | pdf | ≤ 32 MB |
  | png, jpg, jpeg, webp, gif | ≤ 20 MB |
  | txt, md, csv, json | ≤ 256 KB y ≤ 100 000 caracteres |

- Hasta 3 intentos de generación. Se reintenta si el resultado viene vacío o si algún tier no trae recomendaciones.
- Las bandas de tiers se calculan en el servidor.
- Las etapas generadas se guardan como cuestionarios hijos (`parent` = raíz, `origin_session_id` = sesión).

### 7.9 Evaluación de respuestas por IA (follow-ups)

- Aplica a preguntas `text` o `audio` con `max_followups > 0` y respuesta no vacía.
- El LLM califica cada criterio de aceptación de 0 a 50.
- **Aprueba** si la respuesta está relacionada con la pregunta **y** el promedio es ≥ 30.
- Si no aprueba: `max_followups` baja en 1 (mínimo 0) y se devuelve `improvement_message`. El resultado es `{type:"evaluation", status:"not_sense", question}`. Si aprueba: `status:"success"`.
- La app del respondente **falla abierta**: si la evaluación falla o se agota el tiempo, el respondente avanza.

### 7.10 Dashboards

- El LLM elige el tipo de dashboard y los gráficos **una sola vez**; se guarda para siempre.
- **Limpieza de la selección del LLM:** se descartan gráficos no permitidos para el tipo, preguntas desconocidas o duplicadas, tipos de control incompatibles y conteos de preguntas fuera de rango.
- `heatmap` y `stacked_bar` exigen que todas sus preguntas compartan la misma escala de respuesta.
- Máximo **10** gráficos. Cada tipo aparece como máximo **2** veces; los excedentes se cambian por un tipo hermano de la misma familia.
- Si no sobrevive ningún gráfico: `502 DASHBOARD_GENERATION_FAILED` y no se guarda nada.
- Si no existe dashboard y el plan no tiene capacidad para `dashboards`: responder 200 con `locked: {feature: "dashboards", reason}` y sin gráficos.
- Las fórmulas de visualización están en §10.9.

### 7.11 Asignaciones

**Progreso:**

- Default: `{completed: miembros de la audiencia con respuesta, total: tamaño de la audiencia, unit: "respondents"}`.
- Follow-up: `{completed: preguntas respondibles contestadas o saltadas, total, unit: "questions"}`.

**Follow-up completo** cuando su sesión compartida tiene `ended_at`. A partir de ahí, crear sesiones y guardar devuelve `409 FOLLOW_UP_COMPLETED`.

**`current_question`** = la primera pregunta respondible sin contestar ni saltar.

**Login del respondente** (`POST /assignations/{id}/sessions`), en este orden:

1. Gate de feature `assignations` sobre el plan del dueño.
2. En follow-up, `409 FOLLOW_UP_COMPLETED` se comprueba antes de buscar al miembro.
3. `400 MISSING_IDENTIFIER` si no hay ni teléfono ni email.
4. Buscar al miembro **solo por teléfono y/o email, nunca por nombre**. Cada identificador enviado debe coincidir.
5. Errores de búsqueda: `403 USER_NOT_FOUND` o `403 NOT_IN_AUDIENCE`.
6. Gate de capacidad `responses`.
7. Emitir un token de sesión de respondente que liga `assignations_id`, `organization_user_id` y `session_id`.

**Sesión compartida (follow-up):** todos los miembros escriben en la misma sesión.

- Al guardar, un valor entrante vacío **no sobrescribe** uno existente (una respuesta gana a un skip).
- Siempre se conservan las revisiones y los valores bloqueados.

**Estado de revisión** (`review_status`): `not_ready`, `in_review`, `changes_requested`, `approved`.

- Una pregunta bloqueada cuenta como aprobada.
- Una revisión solo cuenta para el intento en que se hizo.

**Revisar** (`PUT /assignations/{id}/reviews/{question_id}`): solo en follow-up, solo si está completo, no sobre preguntas bloqueadas ni diapositivas de mensaje.

**Reintento ("enviar a corrección"):**

1. Crear una sesión nueva (intento n+1).
2. Copiar las respuestas aprobadas y **bloquearlas**; vaciar las rechazadas conservando su revisión.
3. Añadir el intento a `attempts`, mover `shared_session_id` a la sesión nueva y borrar `last_reminder_sent_at`.
4. Enviar un email a los destinatarios. Si falla el email, el intento ya existe (`502 RETRY_EMAIL_NOT_SENT`).

**Otras reglas:**

- `type` no se puede cambiar.
- No se puede cambiar la organización si la asignación está en un proyecto (`ASSIGNATION_IN_PROJECT`).
- Si se cambia la organización con audiencia `members`, hay que reiniciar la audiencia.

### 7.12 Proyectos

**Estado de una asignación dentro del proyecto** (primera regla que coincida):

1. Completada → `review` si `in_review`, `correction` si `changes_requested`, si no `approved`.
2. Vencida → `overdue`. "Hoy" se calcula en **UTC−12**, así que el propio día de vencimiento nunca cuenta como vencido en ninguna zona horaria.
3. Cambios pedidos o intento > 1 → `correction`.
4. Tiene algún progreso → `progress`.
5. Si no → `pending`.

**Estado del proyecto:** el primero que se encuentre entre sus asignaciones, en este orden: `review`, `overdue`, `correction`, `progress`, `pending`, `approved`. Sin asignaciones → `empty`.

**`progress_percent`:** promedio redondeado de completed/total de cada asignación (un follow-up completo cuenta 100 %).

**La respuesta también trae** `completed_assignations`, `approved_assignations` y `total_assignations`.

**Reglas:**

- Solo asignaciones **follow-up** de la **misma organización**.
- Una asignación en un solo proyecto.
- Borrar un proyecto desvincula sus asignaciones sin borrarlas.

### 7.13 Recordatorios

Se disparan todos los días a las **13:00 UTC** y también con un botón manual.

- **Qué asignaciones:** follow-ups activos, no completados, no recordados hoy (UTC).
- **Respondentes:** emails de los miembros de la audiencia, deduplicados, con enlace a `/a/{id}`.
- **Usuarios root de la cuenta:** email de estado con progreso, porcentaje, fecha límite, cuántos fueron recordados y enlace a `{ADMIN_URL}/assignations/{id}`. Un dueño que también es miembro solo recibe el recordatorio.
- **Asunto** según los días restantes: sin fecha / vence hoy / vence en N días / N días de retraso.
- **Idioma:** el de la cuenta, `en` o `es` (por defecto `es`).
- El día se marca solo si **ambos** emails salen bien. Un fallo se registra y cuenta como omitido.
- **Envío manual:**

  | Condición | Error |
  |---|---|
  | La asignación no es follow-up | `400 NOT_A_FOLLOW_UP` |
  | El follow-up ya está completo | `409 FOLLOW_UP_COMPLETED` |
  | No hay destinatarios | `422 NO_RECIPIENTS` |
  | El envío falla | `502 REMINDER_NOT_SENT` |

  Si sale bien, devuelve `{recipients: n}`.

### 7.14 Webhooks salientes

**Evento:** `questionnaire.completed` con este cuerpo:

```
{ customer_id, event_type, questionnaire_id,
  data: { id, answers: [{title, value, min?, max?}] } }
```

**Formato de `value` por tipo de control:**

| Tipo | Valor |
|---|---|
| audio | Lista de transcripciones |
| text | String (las listas se unen con `", "`) |
| file | Lista de claves de almacenamiento |
| range | Número, con `min` y `max` |
| Selección | Las etiquetas de opción cuando los valores son numéricos. Lista para checkbox y ranking |

Las diapositivas `message` se omiten.

**Entrega:**

- Primero se comprueba la capacidad `webhook`. Si se rechaza, no se entrega.
- `POST` a cada URL suscrita con los headers `X-Signature: sha256=<HMAC-SHA256 hex del cuerpo>` y `X-Event-Type`.
- Timeouts: 3 s de conexión y 5 s de lectura. **Sin reintentos.**

### 7.15 Onboarding

- La consola marca el flag de forma explícita.
- En cuentas antiguas con `onboarding_completed = null`, el valor se deriva de "¿tiene algún cuestionario?" y se guarda.
- Llamantes sin fila de cuenta reciben `true`.

### 7.16 Estilos de marca (job)

- **Si cambió el sitio web:**
  1. Un navegador headless extrae el CSS del sitio.
  2. El LLM diseña un conjunto de estilos.
  3. Se fuerza contraste de texto ≥ 3:1, con respaldo a blanco/negro o variantes atenuadas.
  4. Se elige el logo entre los candidatos encontrados en la página.
  5. Se ignoran los estilos parciales de la petición.
- **Si no cambió:** los estilos parciales se fusionan en profundidad sobre los existentes (o los por defecto).
- Etapas visibles del job: `reading_website` → `designing_styles` → `saving`.

### 7.17 Scraping de productos y creación de quiz funnel

**Scraping:**

- 1–30 productos por ejecución. Falla si no encuentra ninguno.
- No persiste nada por sí solo.

**Creación del quiz funnel:**

1. La URL de la tienda se reduce a su origen. Si no se envía, se usa la tienda conectada de la plataforma de e-commerce.
2. Se guardan los productos que el comerciante dejó en pantalla, reemplazando el catálogo guardado para ese origen.
3. El LLM genera el cuestionario (tipo `ecommerce`) en el idioma de la cuenta. Variantes: `experience` o `profiling`.
4. Se crea un flujo de 2 estados (`questionnaire` → `quiz_funnel`) con un slug aleatorio en minúsculas.

**Sincronización con la plataforma de e-commerce:**

- Refresca el token si hay token de refresco.
- **Reemplaza todos** los productos de la cuenta.
- Solo se usa el precio de la primera variante.

### 7.18 Generación desde LinkedIn

- Recibe una URL de perfil de LinkedIn e idioma.
- Extrae el perfil y genera un cuestionario diagnóstico.
- [CLIENTE] El cuestionario queda a nombre de una cuenta fija (`XhEFtqTt`).

### 7.19 Asistente de chat (IA)

La conversación la guarda el **cliente**; el backend no guarda estado de chat.

**Modo `create`.** Construye un cuestionario por fases: `basics` → `questions` → `ending` → `review`.

- **Datos básicos obligatorios:** título, tipo (`regular` / `diagnostic` / `chain`), tema, landing, disclaimer y captura de datos. Solo se aceptan si salen de las palabras del usuario, y después necesitan confirmación explícita.
- **Creación:** no se crea nada hasta que el usuario aprueba la revisión del borrador completo. Crear y editar usan la misma lógica de guardado de flujos.
- **Límites:** hasta 100 preguntas; 8 rondas de herramientas por turno; 90 s por turno.
- **Acciones sobre la cuenta:**
  - Las lecturas se ejecutan de inmediato.
  - Las escrituras se encolan (máximo 50) y solo se ejecutan cuando el usuario dice que sí.
  - **Excluido a propósito:** crear API keys e invitar usuarios.
- **Respuestas rápidas** (p. ej. "Sí"/"No", "Ver 5 más"). Las listas se muestran como tablas de 5 o 10 filas.
- Un cuestionario con respuestas no se edita; el chat ofrece duplicarlo.

**Modo `draft`.** Redacta un cuestionario simple (hasta 100 preguntas de origen) sin guardar nada. Termina como `chat-questionnaire-drafted` o `chat-questionnaire-approved`.

**Herramientas de cuenta disponibles para el chat:**

| Área | Herramientas |
|---|---|
| Cuestionarios | `list_questionnaires`, `get_questionnaire`, `list_questionnaire_answers`, `get_questionnaire_analytics`, `set_questionnaire_active`, `copy_questionnaire` |
| Organizaciones | CRUD de organizaciones |
| Asignaciones | CRUD de asignaciones, respondentes, `send_follow_up_reminder` |
| Proyectos | CRUD de proyectos |
| Plan y facturación | `get_plan_and_usage`, `list_plans`, `start_checkout`, `open_billing_portal`, `change_plan`, `revert_plan_change`, `cancel_subscription`, `resume_subscription` |
| Cuenta | `get_profile`, `get_account_settings`, `update_account_language`, `update_account_settings`, `extract_brand_styles`, `list_team_users` |
| Integraciones | `list_api_keys`, `revoke_api_key`, CRUD de webhooks, `get_shopify_connection` |
| Documentación | `list_videos` |

**Resultado del job:**

- `type`: `chat`, `chat-questionnaire-created`, `chat-questionnaire-drafted` o `chat-questionnaire-approved`.
- Campos opcionales: `draft`, `quick_replies`, `actions`. Una acción puede llevar un `job_id` de un job en segundo plano, como el de estilos.

### 7.20 System prompts editables

- 12 claves ([Anexo D](#anexo-d--claves-de-system-prompts)) guardadas como Markdown versionado.
- Algunas claves exigen placeholders: `{admin_instructions}`, `{base_rules}`, `{dashboard_catalog}`. Si faltan: `400 INVALID_PLACEHOLDERS` con `details.missing`.
- Caché de 5 minutos. Si falla el almacenamiento, se usa el texto por defecto de la plataforma.

### 7.21 Correos

| Correo | Disparador | Idioma |
|---|---|---|
| Recuperación de contraseña | Proveedor de identidad. Código + enlace `{ADMIN_URL}/reset-password?code=####` | — |
| Recordatorio a respondentes | §7.13 | Cuenta |
| Estado para el root | §7.13 | Cuenta |
| Reintento / corrección | §7.11 | Cuenta |
| Lead de ventas | `POST /contact`. Asunto `"[Ventas] {name} está interesado en el plan {plan}"`. [CLIENTE] 3 destinatarios fijos | es |
| Bienvenida | Existen plantillas es/en con copia oculta a soporte, **pero nada las envía** | — |

Todos se envían desde `SUPPORT_EMAIL` con plantillas HTML en es y en.

---

## 8. Contrato de la API

### 8.1 Convenciones

- **Prefijo:** `/api/v1`. JSON UTF-8.
- **Éxito:** `{"message": string, "data": ...}`. Algunas rutas antiguas devuelven JSON sin sobre ("bare"); se indican en cada caso.
- **Error:** `{"error": {"code", "message", "details?"}}`.
- **204:** cuerpo vacío.
- **Autenticación de usuario:** `Authorization: Bearer <id_token del proveedor de identidad>`. El token lleva `customer_id`, grupos, email, nombre y `root`.
- **Errores comunes:**

  | Código | Caso |
  |---|---|
  | `400 INVALID_JSON` | Cuerpo que no es JSON válido |
  | `400 VALIDATION_ERROR` | Mensajes aplanados como `"campo: msg; ..."` |
  | `400 INVALID_REQUEST` | Falta un parámetro de ruta |
  | `400 INVALID_UUID` | Id con formato incorrecto |
  | `401 UNAUTHORIZED` | Sin autenticación válida |
  | `403 FORBIDDEN` | "Admin privileges are required." |

- **Paginación**, tres estilos:
  1. **Numerada** con objeto `{page, page_size, total_items, total_pages, has_next, has_previous}`.
  2. **Listado de cuestionarios:** `{items, page, page_size, total, total_pages}`.
  3. **Cursor opaco** (base64) con `next_cursor`: respuestas y respondentes.
- **Búsqueda textual:** por palabras, sin distinguir mayúsculas ni acentos; todas las palabras deben aparecer.
- **CORS:** abierto (`*`).
- **Leyenda de accesos:** **P** = público · **A** = autenticado · **AG** = autenticado y en `ADMIN_GROUPS` · **Own** = el recurso debe ser de la cuenta del llamante (salvo `Admin`) · **Cap(x)** = gate de capacidad · **Feat(x)** = gate de feature.

### 8.2 Autenticación y cuenta

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `POST /register` | P | `email` (normalizado a minúsculas), `password` (≥ 8), `name` (1–50), `language` (default `es-CO`), `source` (default `default`) | `{customer_id, user:{email, name, root:true, role:"Customer-Admin"}}`. No devuelve tokens: el cliente luego inicia sesión en el proveedor de identidad. `409 EMAIL_ALREADY_EXISTS`. Evento `UserRootRegistered` |
| `POST /login` | P | `{email, password}` | [DEUDA] Login antiguo contra un único usuario configurado. Devuelve `{token}` con expiración de 730 h. Con credenciales incorrectas da 500. Solo lo usa un acceso de QA |
| `POST /password-recovery` | P | `email` | Siempre 200 ("If the account exists, a recovery code is on its way"), para no revelar qué cuentas existen. `429 TOO_MANY_ATTEMPTS` |
| `POST /password-recovery/confirm` | P | `email`, `code` (1–64), `password` (≥ 8) | `400 INVALID_RESET_CODE` (también si el usuario no existe), `400 EXPIRED_RESET_CODE`, `400 INVALID_PASSWORD`, `429 TOO_MANY_ATTEMPTS` |
| `POST /users` | AG, Cap(users) | `email`, `password` (≥ 8, queda como permanente), `name` (1–50), `role` ∈ asignables | `{email, name, root:false, role, customer_id}`. `400 INVALID_ROLE`, `409 EMAIL_ALREADY_EXISTS`. Evento `UserCreated` |
| `GET /users` | A | — | `{users:[{email, name, root, role, customer_id}]}`. El root primero, luego por nombre |
| `GET /profile` | A | — | `{customer:{customer_id, name, email, language, logo_url, website, styles}}` |
| `GET /customer/onboarding` | A | — | `{onboarding_completed}` (§7.15). Sin gate |
| `PATCH /customer/onboarding` | A | `{completed: bool}` (no admite campos extra) | `404 CUSTOMER_NOT_FOUND` |

No existen: invitación por email, edición de usuarios, borrado de usuarios, MFA.

### 8.3 Settings, uso, planes y facturación

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /customer/{customer_id}/settings` | P | — | `CustomerSettings` (`max_files` default 10). `404 CUSTOMER_NOT_FOUND` |
| `PATCH /customer/{customer_id}/settings` | AG; si no es `Admin`, solo sobre su propia cuenta; Cap(profile) si cambia algo distinto de `language` | Al menos un campo; no admite campos extra. `language` nunca null. IDs de tracking: vacío o null los borra. `max_files`: entero estricto 1–20; null lo devuelve al valor por defecto | Evento `ProfileEdited` |
| `GET /customer/usage` | A | — | `{customer_plan, plan (sin features), usage:{questionnaires_used, from_at, to_at} \| null, plan_active, features:{feature_id:{allowed, reason, limit, used}}}` (todas las features del catálogo) |
| `GET /plans` | A | — | `{current_plan_id, current_billing_interval, active_until, scheduled_plan_id, scheduled_billing_interval, cancel_at_period_end, trial_eligible, trial_end, discount, plans[]}`. Cada plan: `{id, plan_name, plan_description, price_amount, currency, purchasable, yearly_price_amount, yearly_purchasable, max_questionnaires, max_responses, trial_days, features:[{feature_id, feature_name, limit}]}`. Primero los planes con precio (por precio), luego por nombre. Los campos de programación y cancelación se leen en vivo de la pasarela; si falla, se asume "nada pendiente" |
| `POST /checkout/session` | A (sin gate) | `plan_id`, `billing_interval` (`month` default \| `year`) | `{checkout_url}`. `404 PLAN_NOT_FOUND`, `400 PLAN_NOT_PURCHASABLE`, `502 STRIPE_UNAVAILABLE` |
| `POST /checkout/plan-change` | A | Igual | `{type:"changed", plan_id, billing_interval, change:"upgrade"\|"downgrade", effective_at}` o `{type:"checkout", plan_id, billing_interval, checkout_url}` si no hay suscripción. `400 SAME_PLAN`, `404`, `400 PLAN_NOT_PURCHASABLE`, `502` |
| `POST /checkout/plan-change/revert` | A | — | `{subscription_id, plan_id, renews_at}`. `400 NO_SUBSCRIPTION`, `400 NO_SCHEDULED_CHANGE` |
| `POST /checkout/cancel` | A | — | `{subscription_id, plan_id, active_until}`. `400 NO_SUBSCRIPTION` |
| `POST /checkout/resume` | A | — | `{subscription_id, plan_id, renews_at}`. Idempotente. `400 NO_SUBSCRIPTION`; 502 si el periodo ya venció |
| `POST /checkout/portal` | A | — | `{portal_url}` del portal de facturación de la pasarela, con retorno a `{ADMIN_URL}/profile/plans`. `400 NO_STRIPE_CUSTOMER` |
| `POST /checkout/webhook` | P + firma | Evento de la pasarela | Texto plano: 401 si la firma es mala, 500 para forzar reintento, 200 OK |
| `POST /contact` | A | `type` (`plan`), `plan_id` (requerido si type = plan), `email`, `phone` (1–50) | Envía el lead de ventas. `404 PLAN_NOT_FOUND`, `502 EMAIL_UNAVAILABLE` |

### 8.4 Cuestionarios, flujos y sesiones

**`GET /questionnaire`** · A

Parámetros de consulta:

| Parámetro | Valores | Error |
|---|---|---|
| `type` | `default`, `quiz_funnel`, `diagnostic`, `process_mapping` | `INVALID_TYPE` |
| `sort_by` | `created_at`, `updated_at` | `INVALID_SORT` |
| `order` | `asc`, `desc` (default `desc`) | `INVALID_ORDER` |
| `is_active` | `true`, `false`, `1`, `0` | `INVALID_IS_ACTIVE` |
| `parent` | `ROOT` (default) o un id | — |
| `page` | ≥ 1 (default 1) | — |
| `page_size` | ≤ 100 (default 20) | — |
| `search` | Texto, se recorta a 200 caracteres. Busca en el título | — |

- Respuesta: `{items, page, page_size, total, total_pages}`.
- Cada ítem: `questionnaire_id, customer_id, parent, origin_session_id, title, description, created_at, updated_at, is_active, on_completed, status, landing_page, capture_user_data, question_count, is_chain, slug, type`.
- `Admin` ve todas las cuentas.

**Creación, edición y gestión:**

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `POST /questionnaire` | AG, Cap(según tipo: `regular` / `diagnostic` / `chain` / `quiz-funnel`) | Un **flujo**: `{slug, states[], cta, layout?, result_copy?}`. El estado `questionnaire` contiene el cuestionario completo | `{questionnaire_id}`. `409 SLUG_ALREADY_IN_USE`. Evento `QuestionnaireCreated` |
| `PUT /questionnaire` | AG, Own | Igual, más `questionnaire_id` | `data:null`. `404`, `403`, `409 QUESTIONNAIRE_ALREADY_ANSWERED`, `409 SLUG_ALREADY_IN_USE` |
| `GET /questionnaire/{id}` | A, Own | — | Cuestionario completo, con los tiers del diagnóstico fusionados en `on_completed`. `404 QUESTIONNAIRE_NOT_FOUND`, `403` |
| `PATCH /questionnaire/{id}` | AG, Own | Exactamente `{is_active: bool estricto}` | La fila actualizada |
| `POST /questionnaire/{id}/copy` | AG, Own, Cap(tipo del original) | — | El cuestionario nuevo (§7.5). Evento `QuestionnaireCreated` |
| `GET /questionnaire/{id}/prompts` | AG, Own | — | `{prompts:[{..., text}]}` en orden, con el texto cargado |

**Respuestas y analítica:**

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /questionnaire/{id}/answers` | A, Own | `status`: `completed` (default), `filling`, `filled_out`, `processing`, `all` (alias antiguos `in_progress`, `submitted`). `limit`: 20, 50 o 100 (default 100). `cursor`. `include_chain=true` | `{items: sesiones enriquecidas con datos del miembro, next_cursor}` |
| `GET /questionnaire/{id}/analytics` | A, Own, Cap(analytics) | — | `{questionnaire_id, questions_analytics, total_sessions, sessions_completed}`. Evento `AnalyticsFetched` |
| `GET /questionnaire/{id}/dashboard` | A, Own, Cap(analytics) | — | `{questionnaire_id, customer_id, type, charts[], created_at, questions:[{id, title, type, options:[{label, value}], min, max}], locked}`. `502 DASHBOARD_GENERATION_FAILED`. Evento `DashboardGenerated` |
| `GET /questionnaire/{id}/dashboard/data` | A, Own, Cap(analytics) | — | Ver §10.9. `502 ANALYTICS_UNAVAILABLE` |

**Flujos y generación:**

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /questionnaire/find?url=` | P | URL de la página | Bare: `{questionnaire_url: {FRONTEND_URL}/f/{flowId}}`. Busca el flujo más reciente cuya URL de tienda coincida (normalizada), con respaldo al origen del sitio. 400 si falta `url`; 404 si no hay coincidencia. Lo usa el widget de la tienda |
| `GET /flow/{identifier}` | P | id de flujo, slug o id de cuestionario | `{id, slug, detail, states, customer_id, questionnaire_id, source_url, cta, layout, result_copy, created_at, updated_at}`. `404 FLOW_NOT_FOUND` si el cuestionario está asignado a una organización y se buscó por slug o id de flujo |
| `POST /questionnaire/quiz-funnel` | AG, Cap(quiz-funnel) | `type` (`experience` \| `profiling`), `source_url?`, `products?` | `202 {job}`. Resultado: `{type:"create_quiz_funnel", flow, questionnaire_url}` |
| `POST /questionnaire/linkedin` | P | `linkedin_url` (`https?://([a-z]{2,3}\.)?(www\.)?linkedin.com/in/...`), `language` (`en`/`es`; cualquier otro valor pasa a `es`) | `202 {job}`. Resultado: `{type:"linkedin_questionnaire", questionnaire_id}` |
| `POST /questionnaire/prompt` | P | `questionnaire_id` (el padre), `answers: [{question, answer}]`, `session_id?` | `202 {job}`. Resultado: `{type:"prompt_questionnaire", questionnaire_id}` |

**Sesiones del respondente:**

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `POST /questionnaire/{questionnaire_id}/session` | P (Bearer de respondente opcional). Cap(responses) solo en cuestionarios raíz | — | Bare: la sesión (copia del cuestionario con `session_id`, `started_at`, `flow_id`, `status:"filling"`). `on_completed` se reduce a `{type}` para no exponer la configuración de puntaje. 404 si está asignado a una organización, no existe o está inactivo. Evento `QuestionnaireSessionCreated` |
| `PUT /questionnaire/session` | P (Bearer si es de asignación) | La sesión completa | Guarda el progreso. En follow-up fusiona según §7.11. `409 FOLLOW_UP_COMPLETED`. Evento `QuestionnaireSessionUpdated` |
| `POST /questionnaire/session` | P; en asignaciones exige el Bearer de respondente | La sesión completa + `user_data?` | Ejecuta §7.7. Quiz funnel → `{job}`. Otros tipos → `{type, ...resultado, cta?, layout?, result_copy?}`. `409 FOLLOW_UP_COMPLETED`. [DEUDA] Con un token de asignación inválido responde 200 sin procesar nada |
| `GET /questionnaire/session/{session_id}/results` | P (UUIDv4) | — | `{session_id, customer_id, questionnaire_id, cta, layout, result_copy, products, ai_team_profile, diagnostic}`. `404 SESSION_RESULTS_NOT_FOUND` |
| `GET /questionnaire/session/{session_id}/chain` | A, Own | — | `{stages:[...], total_stages}`: todas las etapas que recorrió el respondente. `404 SESSION_NOT_FOUND` |
| `POST /questionnaire/session/{session_id}/answers/{question_id}/evaluate` | P | El objeto pregunta | `{job}` (§7.9) |

**Archivos y transcripción:**

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /transcription/token` | P | — | `{token}`: secreto efímero (~1 min) para la transcripción en tiempo real. Uno por grabación |
| `POST /signed-urls` | P | `filename`, `content_type` (`type/subtype`), `customer_id`, `upload_type` (`answer_media` default \| `prompt`), `session_id` y `question_id` (requeridos para `answer_media`) | `answer_media`: subida firmada tipo formulario `{url, fields, key, expires_in:900}`. Clave `{customer_id}/{session_id}/{question_id}/{md5}{ext}`. Tamaño de 1 byte a 500 MB. `prompt`: subida firmada directa `{url, key, expires_in}`. Clave `prompts/{customer_id}/{uuid}{ext\|.txt}` |
| `POST /answers-media/download-urls` | A | `key` (exactamente 4 segmentos no vacíos separados por `/`, sin `..`, ≤ 1024), `disposition` (`inline` \| `attachment` default) | `{url, expires_in:900}`. 403 si el primer segmento de la clave no es el `customer_id` del llamante (salvo `Admin`) |

### 8.5 Jobs y estilos

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /jobs/{job_id}` | P | — | `{job:{job_id, job_type, status, result, stage, created_at, updated_at}}`, nunca el payload. `404 JOB_NOT_FOUND`, `500 INVALID_JOB_DATA` |
| `POST /styles` | AG, Cap(styles) | `website?` (vacío = ninguno), `styles?` (parcial, camelCase, validado) | `{job_id}` (§7.16) |
| `GET /styles?customer_id=&questionnaire_id=` | P | Uno de los dos es obligatorio (400 si faltan ambos). Solo se usa `customer_id` | `{styles \| null}` |

### 8.6 Productos y plataforma de e-commerce

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /customer/{customer_id}/products` | P | — | Bare: `[{product_id, name, description, price, image_url, product_url}]` |
| `GET/POST/PUT/DELETE /customer/{id}/products/{pid}` | A | — | CRUD de productos que usa la consola en la pantalla oculta `/products` |
| `POST /scrapers/products` | A | `url`, `limit` (1–30, default 10) | `202 {job}`. Resultado: `{type:"scrape_products", products}`. No persiste nada |
| `GET /auth/shopify?shop=` | A | `shop` que coincide con `[a-z0-9][a-z0-9-]*\.myshopify\.com` | `{url}` de OAuth con alcance de solo lectura de productos |
| `GET /auth/shopify/callback?code&shop&state` | P | — | Intercambia el código por token + token de refresco y los guarda. Sincroniza productos en la primera conexión. Devuelve un HTML que se cierra solo. `400 INVALID_REQUEST`, `404 CUSTOMER_NOT_FOUND`, `400 TOKEN_EXCHANGE_FAILED` |
| `GET /shopify/connection` | A | — | `{shop \| null}` |
| `GET /shopify/sync/products` | A | — | §7.17. `400 SHOPIFY_NOT_CONNECTED`, `400 SHOPIFY_TOKEN_EXPIRED` |
| `POST /shopify/webhooks/customers/data_request`, `/customers/redact`, `/shop/redact` | P + HMAC | Cuerpo crudo | Verifican HMAC-SHA256 (base64) contra el secreto de la app. Solo registran y responden 200; 401 si la firma es mala |

### 8.7 Organizaciones

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /organizations` | A | — | Bare: `{organizations:[{...org, organization_users:[...]}]}`. `Admin` ve todas |
| `POST /organizations` | AG, Cap(organizations) | `name` (1–120, sin espacios de sobra, no vacío), `domain_email?` (con forma de dominio, minúsculas), `description?` (≤ 1000), `active` (default true), `organization_users[]`: `{organization_user_id? (UUIDv4), name (1–200), email?, phone? (≤ 50), role? (≤ 120), area? (≤ 120)}`. Cada miembro con email o teléfono; emails únicos en la lista. No admite campos extra | 201 con la organización y sus miembros. `409 DOMAIN_EMAIL_CONFLICT`. Evento `OrganizationCreated` |
| `PUT /organizations/{id}` | A (+ **DEBERÍA** exigir Own; ver §15) | Parcial, al menos un campo. Si viene `organization_users`, se **reconcilia**: se empareja por id, luego por email, luego por nombre + teléfono; los miembros existentes que no coinciden se borran | `404 ORGANIZATION_NOT_FOUND` |
| `DELETE /organizations/{id}` | A (+ **DEBERÍA** exigir Own) | — | 204. Evento `OrganizationDeleted`. [DEUDA] No borra los miembros |

No hay `GET /organizations/{id}`: la consola filtra el listado del lado del cliente.

### 8.8 Asignaciones

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /assignations?page&page_size&type` | A | `page_size` default 20, máx. 100. `type`: `default` \| `follow_up` | `{assignations:[enriquecidas], pagination}`. Las más nuevas primero; `Admin` ve todas |
| `POST /assignations` | AG, Cap(assignations) | `organization_id`, `questionnaire_id` (UUIDv4), `name` (1–200), `description?` (≤ 2000), `max_follow_ups` (≥ 0), `active` (default true), `type`, `due_date?` (solo follow-up), `audience` (default `{type:"all"}`), `questions` (≥ 1). No admite campos extra | `201 {questionnaire_url: {FRONTEND_URL}/a/{id}, assignation_id}`. `409 QUESTIONNAIRE_ALREADY_ASSIGNED`, `400 AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`. Evento `AssignationCreated` |
| `GET /assignations/{id}` | P (la usa la página del respondente) | — | Asignación enriquecida con `attempts` detallados, `organization_name`, `questionnaire_name`, `questionnaire_url`, `completed`, `review_status`. Solo para llamantes anónimos: Feat(assignations) → 429 |
| `PUT /assignations/{id}` | A, dueño o `Admin` | Parcial. `type` prohibido ("type cannot be changed"). `due_date:null` la borra | `404`, `400 ASSIGNATION_IN_PROJECT`, `400 VALIDATION_ERROR`, `400 AUDIENCE_MEMBER_NOT_IN_ORGANIZATION` |
| `DELETE /assignations/{id}` | A, dueño o `Admin` | — | 204. Evento `AssignationDeleted` |
| `POST /assignations/{id}/sessions` | P | `name` (requerido, 1–200), `email?`, `phone?`, `role?`, `area?`. No admite campos extra | Bare: `{token, questionnaire: sesión, flow}` (§7.11). `400 MISSING_IDENTIFIER`, `403 USER_NOT_FOUND`, `403 NOT_IN_AUDIENCE`, `404 ASSIGNATION_NOT_FOUND`, `404 QUESTIONNAIRE_NOT_FOUND`, 429, `409 FOLLOW_UP_COMPLETED` |
| `GET /assignations/{id}/respondents?page_size\|limit&cursor` | A (quien no es dueño recibe una página vacía) | `page_size` default 10, máx. 100 | `{respondents:[{organization_user_id, organization_user_name, organization_user_email, status: pending\|in_progress\|completed, session_id, completed_stages, total_stages, attempts, attempts_detail[]}], next_cursor}`. `400 INVALID_PAGE_SIZE`, `400 INVALID_CURSOR` |
| `POST /assignations/{id}/reminders` | A, dueño o `Admin` | — | `{recipients: n}` (§7.13) |
| `PUT /assignations/{id}/reviews/{question_id}` | A, dueño o `Admin` (la de otra cuenta da 404) | `status` (`approved` \| `rejected`), `comment?` (≤ 1000; vacío pasa a null) | `{question_id, review, review_status}`. `400 NOT_A_FOLLOW_UP`, `409 FOLLOW_UP_NOT_COMPLETED`, `404 QUESTION_NOT_FOUND`, `400 QUESTION_LOCKED` |
| `POST /assignations/{id}/retries` | A (+ **DEBERÍA** exigir dueño) | — | `201 {attempt, session_id, recipients}`. `409 REVIEW_INCOMPLETE`, `400 NOTHING_TO_RETRY`, `502 RETRY_EMAIL_NOT_SENT` |

### 8.9 Proyectos

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `GET /projects?page&page_size&status&q` | A | `page_size` default 10, máx. 100. `status` ∈ `review`, `progress` (incluye `pending`), `correction`, `overdue`, `approved`. `q` busca en nombre + organización | `{projects, pagination}`. `400 INVALID_PROJECT_STATUS` |
| `GET /projects/{id}` | A | — | Proyecto enriquecido (§7.12). `404 PROJECT_NOT_FOUND` |
| `POST /projects` | A, Feat(assignations) | `organization_id`, `name` (1–200), `description?` (≤ 2000), `due_date` (requerido), `assignation_ids[]` (deduplicados). No admite campos extra | 201. `404 ORGANIZATION_NOT_FOUND`, `404 ASSIGNATION_NOT_FOUND`, `400 ASSIGNATION_ORGANIZATION_MISMATCH`, `400 ASSIGNATION_NOT_FOLLOW_UP`, `409 ASSIGNATION_IN_OTHER_PROJECT` |
| `PUT /projects/{id}` | A | `name`, `due_date` y `assignation_ids` no pueden ser null; `organization_id` no se puede cambiar (400). `assignation_ids` **reemplaza** el conjunto | — |
| `DELETE /projects/{id}` | A | — | Desvincula las asignaciones y borra el proyecto. 204 |

### 8.10 Chat

`POST /chat` · AG, Cap(chat).

**Entrada:**

- `messages[]`: 1–40 elementos `{role: user|assistant, content 1–20000}`. El último debe ser `user`.
- `mode`: `create` (default) \| `draft`.
- `draft?`: el borrador actual.
- `item?`: `{kind: questionnaire|organization|assignation|project, id}`.

**Salida:** `202 {job}` (§7.19).

### 8.11 API keys, API externa y webhooks

| Método y ruta | Acceso | Entrada | Salida / errores |
|---|---|---|---|
| `POST /api-keys` | A, Feat(api) | `name` (1–100), `expiration_days?` (1–3650) | `201 {api_key: "QAIRE-" + 64 hex}`. **Se muestra una sola vez** |
| `GET /api-keys` | A | — | Solo las activas: `[{id, name, created_at, expires_at, last_used_at}]`, las más nuevas primero |
| `DELETE /api-keys/{id}` | A, Own | — | Revoca. `404 API_KEY_NOT_FOUND` |
| `GET /external/questionnaires?page&page_size` | `X-API-Key`, Cap(api) en cada llamada | `page_size` default 50, máx. 50 | `{questionnaires:[{id, flow_id, slug, title, description, is_active, type, created_at, updated_at}], pagination}`. `401 INVALID_API_KEY` (el mismo mensaje si falta, está revocada o vencida). Marca `last_used_at`. Evento `ApiUsage` |
| `GET /external/questionnaires/{id}/answers?page&page_size` | `X-API-Key` | — | `{questionnaire_id, sessions:[{id, answers:[{title, value, min?, max?}]}], pagination}`, las más nuevas primero. 404 si es de otra cuenta |
| `POST /webhooks` | A, Feat(webhook) | `url` (solo https), `event_type` (`questionnaire.completed`), `method` (`POST`) | 201 con el webhook |
| `GET /webhooks` · `PUT /webhooks/{id}` (parcial) · `DELETE /webhooks/{id}` (204) | A, Own | — | `404 WEBHOOK_NOT_FOUND` si es de otra cuenta |

### 8.12 Videos de documentación

`GET /videos?language=es|en` · A. Ordenados por `order` y luego por título. `400 INVALID_LANGUAGE`.

### 8.13 Super-admin (`/admin/*`, solo `Admin`; `Customer-Admin` recibe 403)

**Cuentas:**

| Ruta | Descripción / errores |
|---|---|
| `GET /admin/customers?page&page_size&search` | `{customers:[{id, name, email}], pagination}`. `page_size` default 10, máx. 100. `search` busca por subcadena en id, nombre o email |
| `GET /admin/customers/{id}/users` | `{users:[{id, name, email}], pagination}`. `404 CUSTOMER_NOT_FOUND` |
| `GET /admin/customers/{id}/plan` | `{customer_plan \| null}`. `404 CUSTOMER_NOT_FOUND` |
| `PUT /admin/customers/{id}/plan` | `{plan_id, from_at, to_at, billing_interval}`. `400 UNKNOWN_PLAN`, `400 INVALID_DATE_RANGE`, 404 |
| `GET /admin/customers/{id}/usage` | `{customer_id, customer_plan, plan, plan_active, usage:{from_at, to_at, features} \| null, features}`. Nunca da 404 |
| `PUT /admin/customers/{id}/usage` | `{features:{feature_id: int ≥ 0}}`. Se fusiona en los contadores del periodo abierto. `400 UNKNOWN_FEATURE`, `503 USAGE_UNAVAILABLE` |

**Catálogo de features:** `GET`, `POST /admin/features`, `PUT /admin/features/{id}` (reemplazo completo), `DELETE /admin/features/{id}`.

- Cuerpo: `feature_name` (1–100), `feature_description` (≤ 1000). El id es el slug del nombre y no cambia.
- Errores: `409 FEATURE_ALREADY_EXISTS`, `400 INVALID_NAME`, `404 FEATURE_NOT_FOUND`.
- `POST` emite `FeatureCreated`.

**Catálogo de planes:** `GET`, `POST /admin/plans`, `PUT /admin/plans/{id}` (reemplazo completo), `DELETE /admin/plans/{id}`.

- Cuerpo: campos de §6.4.
- Errores: `400 UNKNOWN_FEATURE`, `409 PLAN_ALREADY_EXISTS`, `404 PLAN_NOT_FOUND`, `400 INVALID_STRIPE_PRICE`. Este último cubre: el precio no existe, está archivado, tiene otro intervalo, o no coincide en monto o moneda.

**Cupones** (solo en la pasarela): `GET`, `POST /admin/coupons`, `DELETE /admin/coupons/{promotion_code_id}` (desactiva el código).

| Campo | Regla |
|---|---|
| `code` | Se pasa a mayúsculas; `^[A-Z0-9_-]{3,32}$` |
| `type` | `percent` \| `amount` |
| `percent_off` | 0 < x ≤ 100 |
| `amount_off` | > 0, con `currency` (3 letras) |
| `duration` | `once` \| `repeating` \| `forever` |
| `duration_in_months` | ≥ 1, solo con `repeating` |
| `plan_ids[]` | Vacío = todos los planes comprables |
| `billing_intervals` | ≥ 1, sin repetir. `repeating` solo admite `["month"]` |
| `expires_at` | Fecha no pasada; vale hasta las 23:59:59 UTC de ese día |
| `max_redemptions` | ≥ 1 |

- Estado derivado: `active`, `expired`, `exhausted`, `inactive`. También `times_redeemed`.
- Errores: `400 INVALID_COUPON`, `400 UNKNOWN_PLAN`, `400 PLAN_NOT_PURCHASABLE`, `400 COUPON_CURRENCY_MISMATCH`, `400 COUPON_INTERVAL_NEEDS_OWN_PRODUCT`, `409 COUPON_CODE_TAKEN`, `404 COUPON_NOT_FOUND`.

**Videos:** CRUD de `/admin/videos` con los campos de §6.22.

**System prompts:**

| Ruta | Descripción |
|---|---|
| `GET /admin/system-prompts` | Resúmenes `{key, description, required_placeholders, source: s3\|default, updated_at, updated_by, version_id}` |
| `GET /admin/system-prompts/{key}?version_id` | Añade `text` |
| `PUT /admin/system-prompts/{key}` | `text` (1–65 536, no en blanco). `400 INVALID_PLACEHOLDERS` |
| `GET /admin/system-prompts/{key}/versions` | Historial de versiones |

Una clave desconocida da `404 UNKNOWN_PROMPT`.

### 8.14 Salud

`GET /health` · P. Devuelve `data = checks`: 200 "ok" o 500 "error".

---

## 9. App del respondente

### 9.1 Principios

- Mobile-first, sin cuentas ni contraseñas. Solo las asignaciones tienen un login de identidad ligero.
- **Local-first:** el progreso se guarda en el navegador y en el servidor en cada paso.
- Toda la marca (colores, fuente, logo) viene de la cuenta dueña del cuestionario.
- Bilingüe: es/en.

### 9.2 Mapa de rutas

| Ruta | Acceso | Descripción |
|---|---|---|
| `/` | P | Redirección (reemplazo) al sitio de marketing (`https://getmappi.com`) |
| `/q/:id` | P | Cuestionario por id (antiguo pero vigente) |
| `/f/:id` | P | Flujo por id de flujo, slug o id de cuestionario. La URL no cambia entre etapas |
| `/f/:id/generating` | P | Generación de la siguiente etapa por IA |
| `/a/:id` | Login de identidad | Asignación |
| `/a/:id/generating` | Login de identidad | Generación dentro de una asignación |
| `/session/:sessionId/results` | P (envía Bearer si hay token) | **Enlace canónico de resultados**; se puede recargar y compartir |
| `/results`, `/q/:id/results` | P | Resultados antiguos (solo en memoria) |
| `/privacy` | P | Política de privacidad |
| `/tiktok` | P | Cambia el título, dispara un page_view y redirige a la consola |
| `/:id` | P | Antiguo: redirige a `/q/:id` (si `id === 'results'`, muestra los resultados) |
| `/internal/qa/*` | Interno | Herramientas de QA (transcripción local, transcripción en la nube, prueba de errores). **Opcional** en la migración |

**Global:** pantalla esqueleto de carga neutra (sin marca) hasta que se aplican los estilos. Un error no controlado muestra "Algo salió mal / Something went wrong" con un botón "Recargar / Reload".

### 9.3 Pantalla del cuestionario (`/q/:id`)

**Título de la página:** `"<Marca> - {title}"`.

**Prioridad de renderizado** (se muestra el primer caso que aplique):

1. **Límite alcanzado** (la creación de sesión devuelve 429): "The response limit has been reached."
2. **No encontrado** (404 u otro error): "This questionnaire does not exist." con el botón "Want to create this questionnaire?" → URL de la consola.
3. **Procesando** (evaluación por IA en curso): pantalla completa. Eyebrow "One moment", título "We're reviewing your answer", subtítulo "This only takes a few seconds. We're reviewing what you wrote to make sure we have everything we need."
4. **Disclaimer rechazado:** "You can now close this tab."
5. **Modal de disclaimer** (si `disclaimer` no está vacío y no hay consentimiento guardado):
   - "Before you start", el texto del disclaimer (respeta saltos de línea, con scroll a 45vh máx.) y la insignia "Private".
   - "Accept and continue" guarda el consentimiento 24 h.
   - "Not now, thanks" intenta cerrar la pestaña; si no puede, pasa a la pantalla 4.
6. **Tutorial de audio** (si alguna pregunta tiene control `audio` y el tutorial no se ha visto): §9.8.
7. **Landing** (si `landing_page` y aún no hay progreso): eyebrow "Get started", título en serif, descripción, botón "Start questionnaire" y la nota "{count} questions".
8. **Captura de datos** (si `capture_user_data`, al salir de la última pregunta): §9.6.
9. **Vista de preguntas.**

**Modal de reanudación** (sobre 7–9 cuando existe una sesión guardada con progreso):

- Insignia "Saved just now" / "Saved N minute(s)/hour(s)/day(s) ago" (en cubetas de minuto, hora y día).
- "Pick up where you left off" y "You have an unfinished questionnaire. We saved your answers — you can continue from the same question."
- Caja "Question {current} of {total}" con porcentaje y barra.
- "Continue" y el enlace "Start over", con la advertencia "Your {count} saved answer(s) will be deleted".

**Cabecera de preguntas:**

- Logo de la marca o monograma de respaldo.
- Progreso: barra continua en móvil; un punto por pregunta en pantallas medianas en adelante.
- "Step {n} of {total}" y "{pct}%" con `pct = round(step/total×100)`.
- Insignia "Stage {current} of {total}" en flujos multi-etapa.
- Selector de idioma.

**Cuerpo de la pregunta:**

- Eyebrow con el número de orden+1 con cero a la izquierda ("01").
- Título (serif), descripción y disclaimer en cursiva.
- Insignia "{count} attempt(s) left" en un re-intento de follow-up por IA.
- **Banners de revisión** (reintentos de asignación):
  - "Approved / This answer was approved and can't be changed." (pregunta bloqueada).
  - "Needs correction" con el comentario del revisor, o "Your reviewer asked you to answer this question again."
- El control de respuesta.

**Pie de navegación:**

- "Back": deshabilitado en la primera pregunta.
- "Skip": solo si `required === false` y la pregunta no está bloqueada.
- "Next", o "Finish" en la última. Muestra "Saving…" mientras se guarda.
- **Enter** fuera de un campo avanza si no hay bloqueo.
- El pie se oculta en los temas que lo requieren.

**Visibilidad por género:** una pregunta cuyo `visibility` no incluye el género actual se salta sola. Las opciones también se filtran. El género por defecto es `male` y lo fija el tema `gender`.

**Hash de la URL:** refleja el id de la pregunta actual.

### 9.4 Controles de respuesta

Cada pregunta usa **un solo control**: el primero que se pueda renderizar.

- **`radio`**
  - Tarjetas con letra (A, B, C…).
  - Se filtran las opciones por género y se eliminan valores duplicados. Si `value` es null, se usa `label`.
  - Seleccionar desbloquea Next.
- **`checkbox`**
  - Selección múltiple; el valor es un array.
  - Una opción cuyo valor contiene `none`, `n/a`, `not applicable` o `neither` es **exclusiva**: marcarla limpia las demás, y marcar otra la limpia a ella.
  - Next bloqueado mientras no haya nada marcado.
- **`select`**: lista desplegable con placeholder "Select an option". Vacío bloquea Next.
- **`range`**
  - Slider. `min` y `max` salen de las validaciones; por defecto 0 y 10.
  - El control parte en `default_value` si está dentro del rango, si no en `min`. **Esa posición no cuenta como respuesta:** hasta que el usuario lo mueva o toque, se muestra "Move or tap the slider to answer" y Next sigue bloqueado.
  - Un valor fuera de rango muestra el `message` de la validación.
- **`text`**
  - Área de texto de 3 filas con autofoco. Placeholder: `default_value`, luego un ejemplo del preset, luego "Type your answer here...".
  - Vacío o solo espacios bloquea Next. **Enter envía**; no hay salto de línea.
  - Validación `{type:'format', value}`:

    | `value` | Regla | Mensaje |
    |---|---|---|
    | `letters` | Solo letras | "Only letters are allowed" |
    | `numbers` | Solo números, sin espacios | "Only numbers are allowed, no spaces" |
    | `symbols` | Solo símbolos | "Only symbols are allowed" |
    | Combinaciones (`letters,numbers`, etc.) | Clases combinadas | "Only letters and numbers are allowed", etc. |
    | `rfc` | `^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$` con una fecha real 19YY/20YY | "Enter a valid RFC: 3 or 4 letters, a YYMMDD date and the homoclave. E.g.: ABC680524P76" |
    | `nit` | 9–10 dígitos | "Enter a valid NIT: 9 or 10 digits, with no dots or dash. E.g.: 9001234568" |
    | `phone` | `+` inicial, luego dígitos, espacios, `()` o `-`, con 8–15 dígitos | "Enter a phone number with the country code. E.g.: +52 55 0000 0000" |

  - Los espacios internos se permiten salvo en "solo números".
  - Cualquier validación con `pattern` (regex) también se aplica.
  - Teclado móvil: numérico para `nit` y "solo números"; teléfono para `phone`.
- **`email` / `tel` / `phone`**
  - Email: `^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$`. Error "Enter a valid email address (e.g. name@example.com)."
  - Teléfono: se normaliza a dígitos con `+` opcional y debe cumplir `^\+?\d{7,15}$`. Error "Enter a valid phone number: digits only, optional international prefix (e.g. +57)."
  - El error se muestra al salir del campo.
- **`ranking`**
  - Lista reordenable con ratón, táctil y teclado, numerada del 1 al n. Pista "Drag the options to put them in your preferred order".
  - **El orden inicial ya es una respuesta válida** y se guarda de inmediato. El valor es el array de valores en orden.
- **`file`**: §9.9.
- **`audio`**: §9.7.
- **`message`** (o sin opciones): solo muestra contenido. Next habilitado. Se guarda el valor `"viewed"` con timestamp.

**Controles bloqueados** (reintentos): se muestran deshabilitados y nunca se escriben.

### 9.5 Temas especiales (`theme_name`)

| Tema | Comportamiento |
|---|---|
| `gender` | Dos tarjetas grandes con las opciones de `options[0].options`, valores `male` / `female`. Fija el género que filtra preguntas y opciones posteriores |
| `quote` | Pantalla de transición que **avanza sola a los 2500 ms** (o termina si es la última pregunta), sin pie de navegación. Texto: "We're finding the perfect product for your needs." [CLIENTE] Con `livingood`: "Health Profile part of your plan" y "Calculating your Health Profile..." |
| `celebration` | Pantalla de transición de 2500 ms con el título, la descripción y el disclaimer de la pregunta |
| `weight` | Selector de unidad KG (default) o lbs, más un campo numérico. Válido: 20–635 kg o 44–1400 lbs |
| `height` | Selector CM (default) o ft/in. Válido: 50–272 cm o 1.6–8.9 ft |
| `weight-composite` | Tres campos: "Current weight" y "Goal weight" (misma unidad, lbs o kg) y "Current height" (ft/in o cm). Se emparejan por subcadenas de la etiqueta. Se guardan como `"{valor} {unidad}"`. Los tres son obligatorios |
| `jeans-size` | Un selector de sistema (`us-sizes`, `inches`, `centimeters`) decide cuál de `options[1..3]` se muestra como radio |
| `user-capture-data` | Captura de email dentro del flujo, sin pie. "You're Done!" / "Your Personalized Health Profile Is Ready". Campos "Your name (optional)" y email (requerido). Botón "See My Results" termina el flujo |
| `organization-users-login` | Formulario de login de la asignación (§9.10) |

### 9.6 Fase de captura de datos (fin del cuestionario)

- **Textos:**
  - Eyebrow "One last step"
  - Título "Where should we send your results?"
  - Descripción "Share your contact details so we can deliver your personalized recommendations."
  - Nota de privacidad "Your information is private and will only be used to deliver your results."
- **Campos (todos obligatorios):**

  | Campo | Placeholder | Regla |
  |---|---|---|
  | Nombre | "Your full name" | No vacío |
  | Email | "you@email.com" | Regex de email |
  | Teléfono | "Your phone number" | Mientras se escribe solo acepta dígitos y `+` inicial; 7–15 dígitos |

- Los errores aparecen al enviar y se limpian al editar el campo.
- Botón "See my results" con spinner; impide el doble envío.
- Adjunta `user_data: {name, email, phone}` al envío.

### 9.7 Pregunta de audio (respuesta por voz)

| Estado | UI |
|---|---|
| Inactivo | Botón de micrófono grande: "Tap to record your answer" |
| Conectando | Spinner: "Preparing microphone..." |
| Grabando | Botón rojo de stop, 9 barras de onda según el nivel del micrófono, "Listening... mm:ss" y la **transcripción en vivo** |

**Resultado de la grabación:**

- Cada grabación agrega un segmento de texto. El valor es un array de strings.
- Cada segmento se puede borrar ("Remove recording") o regrabar (reemplaza ese segmento).
- Otras acciones: "Add to my answer" y "Record again from scratch".
- Una grabación sin texto muestra "No transcription available."
- Next bloqueado si no hay grabaciones, mientras graba o conecta, o si un follow-up exige un cambio.

**Errores:**

| Caso | Mensaje |
|---|---|
| Contexto no seguro | "Recording requires a secure (HTTPS) connection." |
| Navegador sin soporte | "Your browser does not support audio recording." |
| Sin micrófono | "No microphone was found on this device." |
| Micrófono ocupado | "The microphone is already in use by another application." |
| Otro error de acceso | "Unable to access the microphone." |
| Conexión (el resto) | "We couldn't connect to start recording. Check your internet connection and try again." |

**Permiso denegado.** Se detecta antes de grabar o a partir del error. Abre un panel de ayuda:

- "Microphone access is blocked"
- Explicación de que es un permiso del navegador y no un problema de la plataforma.
- Paso: "Open the [candado] in the address bar → turn on **Microphone** → try again."
- Botón "Try again"
- Nota "We only use your microphone while you're recording."

**Transcripción:**

- Idioma = idioma de la UI (`es`/`en`).
- Se pide un token efímero por grabación (`GET /transcription/token`).
- La cuenta puede definir `transcription_url` para usar un transporte alternativo.

### 9.8 Tutorial de audio (una vez, antes de preguntas con voz)

1. **Intro:**
   - "Quick mic check" / "Let's test your microphone" / "Let's check your microphone. It only takes a few seconds."
   - "Read this out loud:" → "Hello, my microphone is working great."
   - Indicación "Tap here ONCE and wait until it turns red — then start talking".
2. **Prueba:** "Preparing your microphone..." y luego "Listening... read the sentence, then tap to stop".
3. **Resultado:**
   - Éxito: "Your microphone works!" / "We heard you loud and clear…" / "We heard" + transcripción. "Continue to the questionnaire" marca el tutorial como visto por 24 h.
   - Sin texto: "We didn't catch anything. Tap the microphone and try again."
   - Error: "We couldn't complete the mic check" con el detalle. Se reporta al seguimiento de errores.

[DEUDA] No hay forma de saltar el tutorial: un respondente sin micrófono queda bloqueado.

### 9.9 Pregunta de archivo

**Límites:**

- Cualquier tipo de archivo, **hasta 500 MB** cada uno.
- Máximo de archivos = `settings.max_files` de la cuenta (1–20, default 10).

**Formas de agregar archivos:**

- "Choose files" (varios a la vez).
- Arrastrar y soltar.
- **Pegar con Ctrl+V** en cualquier parte de la página. Las capturas pegadas se renombran `screenshot-YYYYMMDD-HHmmss[-n].{ext}`.

**Por archivo:** progreso "Uploading... {progress}%", "Try again" tras un error ("Could not upload your file. Please try again.") y "Remove {name}".

**Otros textos:**

| Situación | Texto |
|---|---|
| Ayuda | "Any file type, up to 500 MB each. Large files may take a while to upload on slow connections." |
| Ayuda de pegado | "…or paste a screenshot with Ctrl+V" |
| Contador | "{count} / {max} files" |
| Límite alcanzado | "You've reached the limit of {max} files. Remove one to add another." |
| Archivo grande | "That file is larger than 500 MB. Please choose a smaller one." |
| Archivos descartados | "{count} file(s) was/were not added: you can attach up to {max} files." |

**Subida:** `POST /signed-urls` y luego una subida directa al almacén de objetos con progreso. **El valor guardado es la clave del objeto**, no una URL.

Next bloqueado hasta que al menos un archivo esté subido y ninguno siga subiendo.

### 9.10 Asignación (`/a/:id`)

**1. Carga:** `GET /assignations/{id}`. Se aplican la marca y el idioma de la cuenta. Con 429 se muestra la pantalla de límite; con cualquier otro error, "This questionnaire does not exist."

**2. Follow-up ya completado** (`completed: true`):

| `review_status` | Mensaje | Pista |
|---|---|---|
| `in_review` / `changes_requested` | "Your answers are being reviewed" (reloj de arena ámbar) | "If something needs changes, you'll receive an email with the link to correct it." |
| `approved` | "Your answers were approved" (check verde) | "Thank you for taking part. There is nothing else to do." |
| null / `not_ready` | "This follow-up has already been completed." | "There is no need to answer it again." |

**3. Reanudación automática sin login** cuando se cumplen las tres condiciones:

- el token guardado tiene `assignations_id` igual al de la URL;
- su `session_id` es el del intento actual (el último de `attempts`), o la asignación no tiene intentos;
- existe progreso local.

**4. Login** (tema `organization-users-login`). Se construye a partir de `questions[0].options`.

- Título "Sign in". Cada opción es un campo; es obligatorio si tiene la validación `required`. El botón se habilita cuando todos los obligatorios están llenos.
- Los campos se reconocen por tipo o por palabra clave en la etiqueta (inglés o español):

  | Clave | Se reconoce por | Etiqueta / placeholder |
  |---|---|---|
  | `email` | tipo `email` o "email"/"correo" | "Email" / "you@email.com" |
  | `phone` | tipo `tel`/`phone` o "phone"/"tel" | "Phone" / "Your phone number" |
  | `role` | "role"/"cargo" | "Role" / "Your role" |
  | `area` | "area"/"área" | "Area" / "Your area" |
  | `name` | "name"/"nombre" | "Full name" / "Your full name" |

- Los campos no reconocidos no se envían.
- Se envían normalizados: nombre en minúsculas, sin acentos, con espacios colapsados; teléfono en dígitos con `+` opcional.
- **Resultados del login:**

  | Respuesta | Resultado |
  |---|---|
  | 409 | Pantalla de completado |
  | 429 | Pantalla de límite |
  | `NOT_IN_AUDIENCE` | "This assessment isn't addressed to you. If you think this is a mistake, contact whoever sent it to you." |
  | `USER_NOT_FOUND` | "We can't find you in this organization. Check the name and email you were invited with." |
  | Otro | "We could not sign you in. Please try again." |

**5. Después del login:**

- Se guarda el token y se envía como `Authorization: Bearer` en las llamadas de sesión y de resultados.
- Se fusionan las respuestas locales del **mismo intento**; nunca las de un intento anterior.
- En follow-up sin respuestas locales pero con progreso compartido en el servidor, el respondente entra en la primera pregunta sin resolver.
- **No hay modal de reanudación.**
- En un reintento, el respondente entra en la primera pregunta rechazada.
- "Start over" vacía las respuestas en su lugar y conserva la sesión y las respuestas bloqueadas.
- Al terminar se borra el token, salvo que la cadena continúe.

### 9.11 Flujo multi-etapa (`/f/:id` o un flujo detrás de `/a/`)

1. Se resuelve el flujo con `GET /flow/{id|slug}` (o se reutiliza una ejecución guardada del mismo flujo) y se precarga la marca. Si el flujo no existe o viene mal formado: "This flow could not be found."
2. El punto de entrada es el único estado `questionnaire`.
3. Al terminar cada cuestionario, el siguiente estado decide:
   - **Otro `questionnaire`:** se carga en el mismo lugar; la URL no cambia.
   - **`prompt`:**
     1. Se aplanan las respuestas a `[{question: title, answer: valores unidos con ", "}]`, sin las no contestadas.
     2. Se navega a `{base}/generating` y se llama `POST /questionnaire/prompt`.
     3. Se hace polling del job hasta tener `questionnaire_id` y se carga esa etapa generada.
   - **null o un estado de visualización** (`diagnostic`, `result`, `quiz_funnel`…): termina el flujo y se muestran los resultados.
4. **Insignia de etapa:** "Stage n of total". Cuenta la entrada más cada `prompt` alcanzable siguiendo `next`, con un tope de 12.
5. **Pantalla de generación:**
   - "One moment" / "Preparing your next questions".
   - 5 mensajes rotativos cada 3.5 s: "We're tailoring the next step from your answers." / "Reviewing what you've told us so far…" / "Picking the questions that matter most for you." / "Almost there — putting the finishing touches on it." / "Thanks for your patience, this only takes a moment."
   - Error: "We couldn't prepare your next questions." con "Try again".
   - **Se puede recargar:** el id del job se guarda y el polling se reanuda.

### 9.12 Envío y resultados

**Al terminar:**

1. Bloquear el envío para que no se envíe dos veces.
2. Limpiar el snapshot local, volver internamente a la pregunta 0 y quitar el hash.
3. `POST /questionnaire/session`. Si devuelve un job (quiz funnel), hacer polling.
4. Si es un cuestionario suelto o la última etapa: navegar a `/session/{session_id}/results`. Si es una etapa intermedia: avanzar el flujo.
5. **Si el envío falla:** volver al cuestionario, re-guardar la sesión para conservar las respuestas y posicionarse en la última pregunta.
6. **Tras el éxito:**
   - Disparar la conversión "Lead" en todos los píxeles configurados, solo si con esto se completó el flujo.
   - Volver atrás desde resultados empieza una sesión nueva.

**Carga de resultados:**

- Si no hay resultado en memoria, se llama `GET /questionnaire/session/{id}/results` y se aplica la marca del `customer_id` devuelto. Si falla, se navega a `/`.
- Pantalla de carga: "Processing" / "We're finishing documenting your answers".
- El tipo se infiere de los datos: `ai_team_profile` → perfil; `diagnostic` → diagnóstico; `products` → ecommerce; si no, default.

**Variantes:**

- **ecommerce**
  - "Your Recommended Products" / "Based on your answers, we have curated these selections specifically for your needs."
  - Cuadrícula de 1, 2 o 3 columnas.
  - Cada tarjeta: imagen con carga diferida (ícono de respaldo), precio (se oculta si es "$0.00"; formato USD en-US), etiqueta "Top Match", nombre (2 líneas), descripción HTML (3 líneas, respaldo "No description available.") y "View in Store" que abre `product_url` en pestaña nueva.
  - Vacío: "No products recommended at this time."
  - **DEBE sanear el HTML de la descripción** (§15).
- **diagnostic**
  - Selector de idioma.
  - Hero: "Successfully completed" / "Thank you so much for your support" / "Here's how you scored across each area." [CLIENTE] El cliente `3zWj6Nrg` cambia el título a "This is the strategic diagnosis of your operation".
  - **Nivel:** "Your level" con el nombre y la descripción del tier. Tier = banda con `min ≤ score ≤ max`; si se solapan, gana el de mayor `min`.
  - **Puntaje global:** "Overall score" como valor/máximo con barra. `pct = clamp(round(score/max×100), 0..100)`.
  - **Por categoría:** "Score by area" / "How you performed in each category.", con barra, % y puntaje/máximo.
  - **Radar** (solo con ≥ 3 categorías; cada eje es el % de su propio máximo): "Your category profile", leyenda "Your score (%)".
  - **"Recommendations"** (viñetas) y **"Action plan"** (numerado). Salen del tier alcanzado; si ese tier no tiene, del tier inferior más cercano que tenga. **Nunca de uno superior.**
  - **Cierre:** "Full report in PDF" / "Includes the detail by category and the action plan." / "Download" ("Preparing your PDF…"). Archivo `YourResults.pdf`, con el color primario de la marca como acento. El CTA va en la misma tarjeta.
  - **`layout`** limita qué elementos aparecen. Sin `layout`: si algún tier tiene `visible:false`, se ocultan puntaje, nivel y categorías.
  - **`result_copy`** sobrescribe los 15 textos.
- **ai_team_profile**
  - Hero "AI Growth · Maturity result": etapa, puntaje/máximo, barra y pista de etapas (hecho/actual/pendiente).
  - "Potential" % y "Percentile", más una cita.
  - "Your 5 dimensions" con barras de color por dimensión.
  - Radar "You vs average vs top 10%".
  - "Your strengths" (dimensiones ≥ 4/6), "Your biggest opportunity", "Your 90-day roadmap" ("Days {days}:").
  - "Download my result (PDF)".
  - Nota "All answers are anonymous · Results are analyzed only at group level".
  - Sin reporte: "We couldn't generate your profile this time. Please try again later."
- **samurai8** [CLIENTE]
  - Textos en español fijos.
  - Tiers Explorador…Maestro con percentiles 100 %, 55 %, 18 %, 10 %, 3 %, 1 %.
  - Máximo 30; cada dimensión máx. 6: Contexto, Datos, Automatización, Calidad, Autonomía. Los puntajes por dimensión se estiman en el cliente.
  - Roadmap de 30 días para Explorador y de 90 para el resto.
  - Fechas en formato `dd.mm.yy`.
- **default**
  - "Your answers have been submitted successfully." / "Thank you for completing the questionnaire. Your answers have been saved and the team can now review them."
  - `result_copy.title`/`subtitle` los sobrescriben.

**CTA (todas las variantes):** `{title, description, button:{text, url}}`. Solo se muestra si hay URL y texto. Abre en pestaña nueva.

**PDF:** se genera del lado del cliente o del servidor (a elección de la implementación) con el contenido del diagnóstico o del perfil.

### 9.13 Privacidad (`/privacy`)

- Tema claro forzado, sin marca del cliente.
- "Legal" / "Privacy Policy" / "Last updated April 20, 2026".
- 9 secciones: Quiénes somos, Información que recolectamos, Cómo la usamos, Uso de IA, Terceros, Retención (90 días tras desinstalar), Derechos, Seguridad, Cambios.
- Contacto por email y pie "© {year}. All rights reserved."

### 9.14 Persistencia local del respondente

Todas las lecturas y escrituras son tolerantes a fallos. Expiración de 24 h.

| Clave lógica | Contenido |
|---|---|
| `questionnaire_session:{qid}` (o `:{assignationId}:{qid}` en asignaciones) | `{questionnaireId, questionId, currentPosition, questionnaire, timestamp}` |
| `questionnaire_disclaimer:{qid}` | Momento de aceptación |
| `questionnaire_audio_tutorial:{qid}` | Momento en que se vio |
| `organization-user-token` | Token del respondente; se borra al completar |
| `assignation_progress:{assignationId}` | `{assignationId, questionnaireId, flowId?, updatedAt}` |
| `flowRun` | Flujo, estado activo, cuestionario activo, respuestas pendientes, job pendiente, sesión pendiente |

**Cuándo se guarda:** en cada cambio de respuesta o de paso (una vez que hay progreso), antes de cerrar la página y justo después de crear la sesión.

**Carga local-first:** si hay un snapshot con menos de 24 h y con `session_id`, se restaura **sin llamar a la red**. Si no, `POST /questionnaire/{id}/session`, que crea una sesión en cada llamada; las llamadas concurrentes se deduplican.

**Autoguardado:** cada Next o Skip hace `PUT /questionnaire/session` con la sesión completa. Skip marca todos los controles con `skipped:true`, valor vacío y timestamp.

**"Start over"** en el modal de reanudación: borra el snapshot y los flags de disclaimer y tutorial, y crea una sesión nueva.

### 9.15 Aplicación de la marca

- Sanitización:
  - Colores hex de 3, 4, 6 u 8 dígitos.
  - Longitudes en px, rem, em o %.
  - Nombre de fuente `^[A-Za-z][A-Za-z0-9 _-]*$` de ≤ 60 caracteres.
  - URL de fuente solo de un proveedor de fuentes permitido (hoy Google Fonts).
- Mapeo:

  | Campo de estilos | Token de tema |
  |---|---|
  | `body.background` | Fondo |
  | `body.color` | Texto |
  | `button.primary.background` | Primario |
  | `button.primary.color` | Texto sobre primario |
  | `a.color` | Acento |
  | `p.color` | Texto atenuado |
  | `input.borderRadius` | Radio |
  | `button.primary.borderRadius` | Radio de botón |
  | `font.family` | Fuente de títulos |

- Las tarjetas usan el fondo del input solo si su contraste con el color del texto es ≥ 2.5; si no, el fondo de la página.
- Bordes y tonos atenuados = mezclas de texto y fondo al 20 % y al 10 %.
- El favicon y la imagen OG no se sobrescriben.

**Idioma:**

- Idioma inicial: el del navegador, con respaldo `es`.
- Se sobrescribe con el `language` de la cuenta, salvo que el usuario ya haya elegido uno a mano en esta visita.
- `<html lang>` se mantiene sincronizado.

### 9.16 Medición de marketing en la app del respondente

| Herramienta | Configuración | Eventos |
|---|---|---|
| Analítica web de la plataforma | ID global | `page_view` en cada cambio de ruta o query (no de hash) |
| Píxel de Meta | `pixel_id` de la cuenta; respaldo: ID global | `PageView` al iniciar y en cada ruta; `Lead` al completar. Solo al píxel resuelto |
| LinkedIn Insight | `linkedin_partner_id` de la cuenta | Conversión `linkedin_conversion_id` al completar |
| Google Ads | `google_ads_id` y etiqueta de la cuenta | `conversion` al completar |
| Mapas de calor / grabación de sesiones | ID global | — |
| Seguimiento de errores | DSN global | Trazas, repeticiones de sesión con error, logs |

---

## 10. Consola del operador

### 10.1 Estructura general

- **Título de página:** "Mappi - {title}". 404: "404 / Oops! Page not found / Return to Home".
- **Composición de rutas autenticadas:** gate de autenticación → gate de onboarding → layout (barra lateral + banner de cliente asumido + banner de uso + contenido).
- **Barra lateral:**

  | Grupo | Entradas |
  |---|---|
  | Design | AI Experience (resaltado), Design Experience, Questionnaires, Customization |
  | Send and track | Organizations, Assignations, Projects |
  | Settings | Users, Integrations, Profile, Documentation |

  - Pie: selector de idioma; bloque de cuenta (iniciales, nombre derivado del email, insignia del plan y email) que enlaza a `/profile`; Logout.
  - Eyebrow "Admin Console". El logo enlaza a `/ai-experience`.
  - Todas las entradas son enlaces reales (clic medio y Ctrl+clic funcionan). Las sub-rutas resaltan su sección.

**Permisos en la UI:**

- Los usuarios de solo lectura ven los controles deshabilitados con tooltip: "Your read-only role can't create resources." / "Your read-only role can't make changes."
- Toda ruta `/new` o `/:id/edit` redirige a quien no tiene permiso de escritura.
- `RequireFeature` espera el veredicto del plan y redirige al listado si la feature no está permitida.
- **Nunca se bloquean por plan:** `/profile/plans`, `/projects`, `/assignations`.

**Sesión:**

- Antes de cada petición, si el token vence en menos de 2 min, se refresca. Las peticiones concurrentes comparten un único refresco.
- Un 401, o un error de red con el token vencido, cierra la sesión y lleva a `/login`.
- Las pestañas se sincronizan entre sí.
- Tras el login se vuelve a la ruta que el usuario intentaba abrir.

### 10.2 Autenticación (rutas públicas)

**`/login`** (pestañas "Sign in" / "Sign up"):

- Insignia "14 days free · no card".
- **"Continue with Google":** OAuth con PKCE contra el proveedor de identidad; dispara el evento de marketing `CompleteRegistration`.
- **Validación:**

  | Campo | Regla | Mensaje |
  |---|---|---|
  | Nombre (solo en sign-up) | Requerido | "Please enter your name." |
  | Email | `^[^\s@]+@[^\s@]+\.[^\s@]+$` | "Enter a valid email address." |
  | Contraseña | ≥ 8 | "Password must be at least 8 characters." |

- **Medidor de fuerza** (sign-up): 1–4 barras (weak / fair / good / strong). Suma un punto por cada uno: ≥ 8 caracteres, ≥ 12, mayúsculas y minúsculas, dígito, símbolo. Se acota a 1–4. Siempre muestra la etiqueta además del color.
- **Mapeo de errores:**

  | Código | Mensaje |
  |---|---|
  | Credenciales inválidas / usuario inexistente | "Invalid email or password." |
  | `EMAIL_ALREADY_EXISTS` | "An account with this email already exists. Try signing in instead." |
  | Contraseña inválida | "Password must be at least 8 characters." |
  | No confirmado | "Your account isn't confirmed yet. Please check your email." |
  | Proveedor no configurado | "Sign-in is temporarily unavailable…" |
  | Otro | "Something went wrong. Please try again." |

- **Flujo de sign-up:** `POST /register` con el idioma normalizado (`es`→`es-CO`, `en`→`en-US`) y el email en minúsculas. Luego inicio de sesión. Toast "Account created. Welcome to Mappi!".
- **Panel de marca:**
  - "Smart questionnaires that end in action."
  - Estadísticas: 32 % "average conversion", 6.4× "more than unguided", "< 8 min" "first result".
  - 3 testimonios que rotan cada 9 s.
  - Enlaces Privacy / Terms / Support ([DEUDA] no llevan a ninguna parte).

**`/sign-in`** (retorno de OAuth):

- Intercambia `?code` por tokens y muestra "Signing you in...".
- Si el error contiene `EMAIL_LINKED_RETRY_LOGIN` (la cuenta de Google se acaba de vincular a una cuenta de contraseña existente), reintenta **una** vez de forma automática.

**`/forgot-password`:**

- Un campo de email → `POST /password-recovery` → `/reset-password`, recordando el email.
- Errores: "Too many attempts. Please try again in a few minutes." / "We could not start the recovery. Please try again."

**`/reset-password`:**

- Campos: email, código (se precarga de `?code`), contraseña nueva y confirmación.
- Validación en orden:
  1. Email válido.
  2. Código requerido: "Enter the code we sent you."
  3. Contraseña ≥ 8.
  4. Coinciden: "The two passwords do not match."
- Errores:

  | Código | Mensaje |
  |---|---|
  | `INVALID_RESET_CODE` | "That code is not valid. Check it and try again." |
  | `EXPIRED_RESET_CODE` | "That code expired. Request a new one." |
  | `INVALID_PASSWORD` | "That password does not meet the requirements." |

- Éxito: toast "Password updated. Sign in with your new password." y redirección a `/login`.

**`/logout`:** tarjeta de confirmación "Sign Out?".

**`/internal/qa-access`:** acceso de QA con el login antiguo. Opcional.

### 10.3 Onboarding (`/onboarding`)

**Cuándo aparece:** para una cuenta con `onboarding_completed:false`.

- **Falla abierta:** un super-admin, la ausencia de cuenta o un error de red cuentan como onboarding completado.
- Cabecera "8–12 minutes" con el enlace "Explore on my own", que marca el flag y lleva a `/ai-experience`.
- Barra de progreso de 7 pasos.

**Paso 1. Objetivo:** "What do you want to achieve with Mappi?". "Continue" deshabilitado hasta elegir uno.

| Objetivo | Descripción | Etiqueta |
|---|---|---|
| Diagnose | "Measure a situation and deliver a level, recommendations, and a plan." | "Scoring + levels" |
| Qualify or recommend | "Direct each person to the right service or next step." | "Segments + CTA" |
| Capture processes | "Understand how a team works through text, audio, and follow-ups." | "Evidence + follow-ups" |
| Collect information | "Create a structured survey without mandatory scoring." | "Regular survey" |

**Paso 2. Workspace:** nombre, idioma y sitio web. [DEUDA] Estos datos no se envían al backend.

**Paso 3. Plantilla:** recomendada según el objetivo.

| Objetivo | Plantilla |
|---|---|
| Diagnose | AI Maturity Diagnostic: 8 preguntas; categorías Estrategia, Datos, Procesos, Talento, Cultura, Tecnología |
| Qualify | Service Qualification: 6 preguntas |
| Capture processes | Process Discovery: 7 preguntas, sobre todo texto libre |
| Collect information | Customer Discovery: 6 preguntas |

- Las preguntas de las plantillas están en español.
- "Use template" o "Start from scratch →" (este último completa el onboarding).
- Slug = `slugify(title)-` + 4 caracteres hex aleatorios.

**Paso 4. Builder:**

- Checklist: "Confirm your questionnaire title", "Edit a question to fit your context", "Review what the person will receive at the end".
- Se pueden editar en línea las 4 primeras preguntas.
- "Save and publish" se habilita con el checklist completo; tras confirmar, crea el cuestionario.

**Paso 5. Publicar:** slug editable con el prefijo `{FRONTEND_URL}/f/`. "Publish and open test" activa el cuestionario.

**Paso 6. Probar:**

- Abre `{publicUrl}?test=1` en una pestaña nueva.
- Hace polling de las respuestas (`limit=1`): primero a los 5 s y luego cada 4 s hasta que aparezca una.
- Muestra "processing" 3 s y después "ready".

**Paso 7. Resultado:**

- Share link (copia), Customize more → `/customization`, Invite team → `/users/new`, Go to dashboard → `/ai-experience`.
- Toda salida marca antes `completed:true`. Si falla: "We couldn't finish your setup…".

### 10.4 Creación con IA (`/ai-experience`, página de inicio)

**Acceso:** permiso de escritura y feature `chat`. `/` redirige aquí.

**Interfaz:**

- Chat a pantalla completa con vista previa en vivo. Botones: Back, "New chat" y mostrar/ocultar preview.
- Encabezado "What do you want to create today?". Saludo "Hi! How can I help you today? ✨".
- Compositor:
  - Enter envía; Shift+Enter hace salto de línea.
  - Máximo 20 000 caracteres; el contador aparece pasado el 90 %.
  - Placeholder "Type your message…".
- Tope de **40 mensajes**: "This conversation reached its message limit. Refresh the page to start over."

**Cada turno:**

1. `POST /chat` y polling del job cada 2 s, con un límite de 5 min.
2. Según el resultado:
   - **`chat-questionnaire-created`:** tarjeta "Questionnaire created" con "Edit questionnaire" y "View questionnaire".
   - **`draft`:** alimenta la vista previa.
   - **`quick_replies`:** botones de un clic.
   - **`actions`:** se invalidan todas las cachés. Si cambió el idioma de la cuenta, la UI cambia de idioma.
   - **Acción con `job_id`** (estilos): polling cada 2 s hasta 5 min. Pasos visibles: "Reading your website" → "Choosing colors and fonts" → "Saving your styles". Termina con la paleta, la fuente y "Open Customization".
3. Un clic en un enlace de entidad envía "Show me the details of the {kind} {name}" junto con `item`.
4. Si falla: "Something went wrong processing your message." con "Retry" (sin Retry cuando el error es de límite de plan).

**Vista previa:** recorre disclaimer → landing → preguntas (se pueden responder) → final. Un final de diagnóstico se puntúa en vivo con las respuestas de la preview. "Watch again" la reinicia.

### 10.5 Creación manual de cuestionarios

**`/questionnaires/new`:** "What do you want to create?"

| Tarjeta | Descripción | Etiqueta | Feature |
|---|---|---|---|
| Regular (Default) | "A classic questionnaire that ends with a custom thank-you message of your choice." | "Best for surveys" | `regular` |
| Diagnostic | "Score each respondent and place them into tiers, each with its own result and action plan." | "Best for assessments" | `diagnostic` |
| Quiz Funnel | "Turn store visitors into buyers with a guided quiz that recommends the right product at the end." | "Imports from your store" | `quiz-funnel` |
| Chaining | "Add your questions plus a prompt; the answers and prompt are used to generate a tailored questionnaire." | "Best for AI generation" | `chain` |

- Una tarjeta que el plan no permite queda deshabilitada con el texto de límite.
- Redirecciones: `/design-experience` → `/questionnaires/new`; `/questionnaires/create/chat` → `/ai-experience`.

**Contenedor de creación** (compartido por Regular, Diagnostic y Chaining):

- Cabecera: breadcrumb; chip "Draft · saved when you create it" o "Editing · saved when you save changes"; stepper de 3 pasos; toggle de preview; Back; acción principal ("Continue" / "Create" / "Save changes").
- Una acción deshabilitada muestra **por qué** en un tooltip.
- Solo se puede ir a un paso si los anteriores son válidos; al editar, todos están disponibles.
- **No hay autoguardado.**
- Crear pide confirmación: "Create the questionnaire?" / "Nothing has been saved yet. Once you confirm, the questionnaire is created in your account." / "Yes, create". Editar guarda sin preguntar.
- Preview en marco de móvil o escritorio. Por debajo de 1100 px se abre como panel lateral.

**Paso 1. Detalles:**

| Campo | Regla |
|---|---|
| Título | Requerido. "Write a title to continue." |
| Slug ("Custom link (slug)") | Pista "Lowercase letters, numbers and hyphens only. Leave empty to generate it from the title." Regla de §6.6. Conflicto: "That custom link (slug) is already in use by another questionnaire. Choose a different one." |
| Descripción | Opcional |
| Enable landing page | Interruptor; activado por defecto en cuestionarios nuevos |
| Disclaimer | Interruptor + texto; si está activo, el texto es obligatorio: "Write the disclaimer text to continue." |

**Paso 2. Preguntas:**

- Reordenar arrastrando. Soltar sobre otra categoría mueve la pregunta a esa categoría. Las preguntas se agrupan por categoría.
- Agregar, duplicar y borrar.
- **Campos por pregunta:**
  - Título (requerido), descripción, disclaimer.
  - Categoría: combobox que permite crear nuevas.
  - Requerida: activada por defecto; fija en activada para los tipos puntuables de un diagnóstico.
  - Tipo de entrada: `radio` "Single selection", `checkbox` "Multiple selection", `select`, `ranking`, `selection_with_score`, `single_selection_with_score`, `text`, `audio`, `range`, `message`, `file`.
- **Campos extra por tipo:**

  | Tipo | Campos extra |
  |---|---|
  | `text` / `audio` | Follow-ups máximos 0–5. Si > 0: criterios de aceptación (hasta 10) |
  | `text` | Tipo de dato: "Free (choose characters)" con casillas All / Letters / Numbers / Symbols (al menos una marcada), o preset RFC / NIT / "Phone / WhatsApp". Se guarda como `{type:'format', value, message:''}` |
  | `range` | Mín. y máx., guardados como validaciones `min` y `max` |
  | Tipos de opción | Opciones con etiqueta; los puntuables también llevan un puntaje numérico por opción |

- **Mensajes de validación:**
  - "Question {{n}} must have a title."
  - "…needs at least one answer choice."
  - "All answer choices in question "…" must have a label."
  - "…must have a numeric score."
  - "…must have a unique score value."
  - "…range input: both min and max are required and min must be lower than max."
- **Codificación:**
  - Opción no puntuable: `value = slug(label)`. Puntuable: `value = puntaje`.
  - Los tipos puntuables viajan como `checkbox`/`radio`. Al cargar, un radio o checkbox con todos los valores numéricos se lee como puntuable.

**Paso 3 según el tipo:**

- **Regular: "When it ends".** Bloques disponibles:
  - Mensaje de agradecimiento: Title y Message ≤ 300 cada uno, enviados como `result_copy.title/subtitle`.
  - Call to action.
  - Capturar datos (`capture_user_data`).

  Validación del CTA:

  | Campo | Regla | Mensaje |
  |---|---|---|
  | Título | Requerido, ≤ 120 | "The call to action title is required." / "…too long." |
  | Descripción | ≤ 200 | "The call to action description is too long." |
  | Texto del botón | Requerido, ≤ 50 | "The button text is required." / "…too long." |
  | URL | Empieza con `http(s)://` | "Enter a full URL starting with http:// or https://." |

- **Diagnostic.**
  - Reglas de preguntas:
    - Todas con categoría: "Every question needs a category — your tiers are built from these categories."
    - Tipos permitidos: selección simple o múltiple (con o sin puntaje), ranking, range, text, audio y file.
    - Al menos una puntuable (`selection_with_score`, `single_selection_with_score`, `ranking`, `range`).
  - **Máximo:**
    - Por pregunta: suma para checkbox, `selection_with_score` y ranking; máximo para el resto (incluye range).
    - Categoría = suma de sus preguntas. Total = suma de todas.
  - **Resultados:**
    - Tiers semilla Beginner / Intermediate / Advanced repartidos de forma pareja en 0..max. Cada tier tiene nombre, desde, hasta y descripción.
    - Reglas de tiers:

      | Regla | Mensaje |
      |---|---|
      | Con nombre | "Give every tier a name." |
      | Rango completo | "Fill in the score range (from and to) for every tier." |
      | from ≤ to | "A tier's "from" score can't be greater than its "to" score." |
      | El primero empieza en 0 | "Your first tier must start at 0." |
      | El último llega al máximo | "Your tiers must reach the top score of {{max}}." |
      | Contiguos | "Tiers can't leave gaps or overlap — each one must start right after the previous." |

    - Bloques: Tier, Total score, Score by area, Recommendations (una por tier), Action plan (una por tier), PDF report, CTA, Capture data.
    - Se guardan como flujo `layout` + `on_completed` de tipo `diagnostic` + `result_copy` (15 textos).

- **Chaining: Prompts.**
  - Línea de tiempo "Starting point" → Prompt N → Questionnaire N, hasta **10** prompts.
  - Final: "Another questionnaire", "Finish" (→ `result`), "Diagnostic" (→ `diagnostic`) o "Quiz funnel" (→ `quiz_funnel`).
  - Los prompts no pueden quedar vacíos.
  - Al guardar:
    1. Se revalida en vivo la feature `chain`.
    2. El texto de cada prompt se sube al almacén de objetos (`POST /signed-urls` con `upload_type:'prompt'` + subida directa).
    3. Se guarda el flujo con estados `prompt` que apuntan a esas claves.

**Pantalla de éxito:**

- "Questionnaire created successfully!" / "Your diagnostic is ready." / "Changes saved".
- Botones: Copy link, View questionnaire, Keep editing, Go to Questionnaires, Create another.

**Quiz Funnel** (`/questionnaires/create/quizfunnel`, feature `quiz-funnel`). Pasos: Store → Products → Generate.

- **Vía plataforma de e-commerce:**
  - La tienda nunca se escribe a mano: viene de `?shop=` (capturado al arrancar y validado) o de `GET /shopify/connection`.
  - Authorize / Reconnect abre la URL de OAuth en una pestaña nueva. "Install" enlaza a la instalación de la app.
  - "Load products": sincroniza y lista los productos.
  - Tras crear: pasos para activar el embed en el tema de la tienda (abrir el editor de temas, guardar y visitar la tienda).
- **Vía sitio web:**
  - URL de la tienda: se agrega el esquema si falta; el host debe tener un punto.
  - Cantidad de productos 5 / 10 / 20 / 30 (default 10).
  - Scraping por job con polling cada 5 s hasta 5 min.
  - Mensajes rotativos: "Analyzing the website...", "Analyzing the online store...", "Polishing your catalog...", "Fixing some details...", "Organizing all the information...".
  - Se pueden quitar productos (confirmación "Are you sure you want to delete your product?").
  - Cargar productos es opcional: Generate hace el scraping si hace falta.
- **Generate:**
  - Tipo "Design Experience" (`experience`) o "Profiling" (`profiling`).
  - Job con polling de hasta 5 min. Resultado `{questionnaire_url, flow:{id, slug, questionnaire_id}}`.
- **Errores:**
  - "Please enter a valid store URL"
  - "Could not access URL. Please ensure it is a public store."
  - "Generation failed. Please try again."
  - "We couldn't start the Shopify connection…"
  - Mensajes propios para `SHOPIFY_NOT_CONNECTED` y `SHOPIFY_TOKEN_EXPIRED`.

### 10.6 Listado de cuestionarios (`/questionnaires`)

**Cabecera:** "Questionnaires", "{{count}} questionnaires" y el botón "New Questionnaire".

**Barra de herramientas:**

- Búsqueda (atajo ⌘/Ctrl+K). [DEUDA] Solo filtra las filas cargadas; el backend ya admite `search`.
- Tipo: All types / Standard / Quiz funnel / Diagnostic / Process mapping.
- Estado: All status / Active / Inactive.
- Orden: "Sort: Created" / "Sort: Updated", Descending / Ascending.
- Zona horaria Local / UTC (preferencia persistente).

**Paginación:** 10 / 20 / 50 / 100 por página (default 10). Páginas numeradas con ventana de 5 y "{{start}}–{{end}} of {{total}}".

**Columnas:**

- Título: enlace a editar, o a la página pública para usuarios de solo lectura. Debajo, "{{count}} questions".
- Tipo: Standard / Quiz funnel / Diagnostic / Process mapping / Chaining.
- Estado con interruptor Active: optimista, se revierte si falla.
- Fecha de creación o de actualización, según el orden.
- Acciones: View (pestaña nueva), Copy link, Edit, Answers y "Analytics" (con insignia "New") que abre el dashboard.

**Vacíos:**

- "No questionnaires created yet" / "Create your first questionnaire to start collecting responses."
- "No matches" + "Clear filters".
- "Nothing matches "{{query}}"." + "Clear search".

### 10.7 Edición (`/questionnaires/:id/edit`)

**Carga:** el cuestionario, el flujo (slug, estados, cta, layout, result_copy) y todas las respuestas, para saber si está bloqueado.

**Sin respuestas**, redirige al editor específico:

| Tipo | Ruta |
|---|---|
| Diagnóstico | `/:id/edit/diagnostic` |
| Cadena | `/:id/edit/prompt` |
| Regular | `/:id/edit/regular` |

**Otros casos** (editor genérico): título, slug con vista previa `…/f/{slug}`, descripción, captura de datos, landing, disclaimer, CTA, preguntas y el botón "Update".

**Bloqueado** (tiene respuestas, o el guardado devuelve 409 sin código de slug):

- Insignia "Locked" y el texto "Locked to preserve answers".
- "Create a copy" → confirmación "Create a copy?" / "A new questionnaire is created in your account, with no answers, ready to edit." / "Yes, create copy" → copiar.

### 10.8 Respuestas

**`/questionnaires/:id/answers`:**

- Cabecera: "Questionnaire Answers: {title}". Clic en el título copia el enlace público.
- **Export to Google Sheets** (§13.9).
- Leyenda de estados: Filling / Filled out / Processing / Completed.
- **Filtros:** estado (default Completed, o All statuses), zona horaria, tamaño de página 100 / 50 / 20 (default 100).
- **Paginación:** por cursor (Previous / Next, "Page N"). El total se obtiene recorriendo todos los cursores.
- **Columnas:**
  - Started At.
  - Name ("Anonymous" si no hay).
  - Email ("N/A" si no hay).
  - Phone (solo si alguna fila tiene).
  - Progreso: contestadas/total, sin contar los temas meta.
  - Estado: barra de 4 segmentos, o "Stage X of N" en cadenas.
  - "View".
- **Cadenas:** pista "The status filter applies to the first stage; the chain may still be in progress."
- Las etapas hijas generadas antiguas aparecen en la tarjeta "Generated questionnaires".

**`/questionnaires/:id/answers/:sessionId`:**

- "Response Details - {{name}}".
- **Resumen:** Email, Name, Phone, Total Time (segundos, o "Uncompleted") y Started At.
- **Cadena de la sesión:** si responde 403 o 404, se usa la sesión suelta y sus resultados.
- **Por etapa:** tabla Question / Answer / Time Spent (diferencia entre timestamps de respuestas). Valores especiales: "Not answered", "Skipped" o "Viewed".
- **Archivos:** una celda por clave. "View" abre un modal de vista previa y "Download" descarga; ambos piden una URL firmada al hacer clic.

  | Tipo | Extensiones | Comportamiento |
  |---|---|---|
  | Imagen | png, jpg, jpeg, gif, webp | Vista previa |
  | PDF | pdf | Vista previa |
  | Audio | mp3, wav, m4a, ogg, aac | Vista previa |
  | Video | mp4, mov, webm | Vista previa |
  | Otros | — | "No preview"; solo descarga |

- **Tarjeta de resultado:**
  - Productos: nombre, enlace y precio.
  - Diagnóstico: puntaje/máximo, tier, barras por categoría, y recomendaciones y plan del tier.
  - Perfil de IA.
  - Si no hay: "No result was generated for this session".
- El enlace de volver regresa a la asignación si se llegó desde ella.

### 10.9 Dashboard (`/questionnaires/:id/dashboard`, feature `analytics`)

**Datos:** `GET .../dashboard` (layout) y `GET .../dashboard/data`:

```
sessions: { total, completed, completion_rate (0..1),
            timeline: [{date, started, completed}],
            by_source,
            duration_seconds: {avg, median} }
questions: [{ question_id, answers_count,
              values: [{value, count}],
              numeric: {count, avg, min, q1, median, q3, max} }]
```

**Resumen** (gratis en todos los planes):

- Completion rate = `round(completion_rate×100)` %, con "X of N sessions completed".
- Sessions = total.
- Average time = `round(avg)` en formato "M min S s".
- Biggest drop-off = la pregunta Qn con la mayor caída respecto al paso anterior; porcentaje = `round(caída/anterior×100)`.

**Embudo:** Started → cada pregunta en orden → Completed.

- Alcance de una pregunta = `max(respuestas de esa pregunta o de cualquier posterior, completed)`, acotado a total. Así el embudo nunca sube.
- `pct = round(count/total×100)`.
- Con más de 6 preguntas, los tramos sin caída se agrupan en "Qa–Qb · no drop-off", con "Show all N questions".
- Mensaje: "Biggest drop at Qn" o "…at the end".

**Gráficos** (13 tipos):

- Las distribuciones se ordenan por conteo y agrupan la cola en "Other": 6 porciones por defecto, 8 en bar, 12 en horizontal bar.
- El histograma rellena cada entero de la escala si el rango es ≤ 30.
- Gauge: fracción = `(avg − min)/(max − min)`.
- Heatmap y stacked bar: columnas por etiqueta de opción.
- **NPS** (escalas 0–10 o 1–10): detractores ≤ 6, pasivos 7–8, promotores 9–10; `NPS = round((promotores − detractores)/total×100)`. Otras escalas se dividen en tercios: bajo, medio, alto.

**Bloqueado** (sin `dashboards`): un tablero de ejemplo desenfocado con el texto del plan y "Get your dashboards" → `/profile/plans`.

**Estados:**

- Carga: mensaje rotativo cada 4.5 s ("Gathering the information…", "Analyzing the data…", "Generating the report…", "Finishing up…"). La primera generación tarda entre 10 y 25 s.
- Vacío: "No answers yet" / "Charts appear here as soon as people start answering this questionnaire."
- Errores: los 4xx no se reintentan; los fallos de generación o de analítica muestran "Try again".

**Insignia de tipo:** Satisfaction / Knowledge / Profiling / Recommendations / Eligibility / Opinion.

### 10.10 Organizaciones

**`/organizations`:** cuadrícula de tarjetas.

- Cada tarjeta: inicial con color derivado de un hash, nombre (truncado a 16), Active/Inactive, dominio, número de miembros. Botones View, Edit y Delete.
- Borrar: "Delete organization?" / "This will permanently delete "{{name}}" and cannot be undone."
- "New Organization" requiere escritura y la feature `organizations`.
- Vacío: "No organizations yet. Create your first one."

**`/organizations/new` y `/:id/edit`:**

- **Campos:** Name (requerido: "Name is required"), Email domain (placeholder "ejemplo.com"), Description, Active (default sí) y la lista de miembros.
- **Miembro:** nombre requerido; email o teléfono ("Each member needs at least an email or a phone"); rol y área opcionales. El email debe ser válido.
  - Duplicados por email o teléfono normalizados: "This member is already in the list".
  - Se editan en línea y se pueden quitar.
- **Normalización:** nombre sin acentos, en minúsculas y con espacios colapsados; email recortado y en minúsculas; teléfono en dígitos con `+`.
- **Aviso de dominio** (no bloquea): "{{count}} member(s) use a domain other than {{domain}}. They will be saved anyway."
- **Importar CSV:**
  - Se quita el BOM UTF-8.
  - Delimitador `,` o `;`: el que más aparezca en la cabecera.
  - Admite campos entre comillas.
  - Alias de cabecera: name/nombre; email/correo; phone/telefono/teléfono; role/rol/cargo; area/área.
  - Faltantes: "The CSV must include a 'name' column" / "…at least an 'email' or 'phone' column".
  - Filas omitidas: "Row {{line}} — {{name}}: {{reason}}". Razones: nombre vacío, email inválido, sin email ni teléfono, ya está en la lista.
  - Éxito: "Imported {{count}} members".
- **Plantilla CSV:** `organization_members_template.csv` con columnas `name,email,phone,role,area`.
- **Guardar:**
  - Crear y luego ir al detalle.
  - Actualizar enviando la lista completa de miembros (los existentes conservan su id).
  - Los errores señalan la fila: "Member {{position}} ({{name}}): {{detail}}".

**`/organizations/:id/view`:** detalle (descripción, dominio, creada, actualizada), tabla de miembros (Name, Email, Phone) y el botón Edit.

### 10.11 Asignaciones

**`/assignations`:**

- **Filtro de tipo:** All / Default / Follow-up.
- **Paginación:** fija de 10 por página, numerada.
- **Columnas:**
  - Nombre, con la línea de organización (enlace) y la de cuestionario (enlace a editar si hay permiso).
  - Chip de audiencia: "Everybody", "2 people", "Area: Sales +1".
  - Chip "Attempt N" cuando un follow-up tiene más de un intento.
  - Tipo (solo en All).
  - Progreso: "{{completed}} of {{total}} people/questions" con barra, o "Completed".
  - Vencimiento (solo en Follow-up): fecha y etiqueta de urgencia.
  - Creada.
  - Interruptor Active.
  - Acciones: View, Edit, Copy link, Delete y "Send reminder" (solo follow-up; deshabilitado si está completo).
- **Borrar:** "Delete assignation?".
- **Recordatorio:** "Send the reminder now?" / "An email with the link to "{{name}}" will be sent to the people who answer it." → toast "Reminder sent to {{count}} recipient(s)".
- **Urgencia del vencimiento** (días calendario enteros):

  | Nivel | Días restantes | Etiqueta |
  |---|---|---|
  | later | > 14 | "in N days" |
  | soon | 8–14 | "in N days" |
  | near | 3–7 | "in N days" |
  | urgent | 0–2 | "due today" (0) o "in N days" |
  | overdue | < 0 | "overdue by N days" |
  | done | Completado | "completed" |

**`/assignations/new` y `/:id/edit`** (crear requiere la feature `assignations`). Asistente Basic → Registration → Save.

- **Basic:**
  - **Tipo** (solo al crear):
    - Default: "The members you pick answer, each with their own session".
    - Follow-up: "The members you pick share one session and receive a daily reminder until it is completed".
  - **Fecha límite** (solo follow-up, opcional, puede estar en el pasado). Pista: "The day this follow-up should be completed. Reminders keep arriving until it is."
  - **Organización\***: búsqueda del lado del cliente por todas las palabras, sin mayúsculas ni acentos.
  - **"Who responds?"** (deshabilitado sin organización):
    - Everybody; People (casillas con búsqueda por nombre, email, área o rol); Area; Role.
    - Área y rol listan los valores distintos con conteo de personas.
    - Contador en vivo: "N people will respond".
    - Cambiar la organización reinicia la audiencia a Everybody.
  - **Cuestionario\***: búsqueda en el servidor con debounce de 300 ms y scroll infinito de 20 en 20; solo se conserva la última respuesta.
  - **Nombre\***.
  - **Descripción:** "An internal note about this assignation. Respondents never see it."
  - **Validaciones:** "Organization is required", "Check at least one person, area or role, or choose Everybody", "Questionnaire is required", "Name is required", "Enter a valid due date".
  - **Conflicto:** si el cuestionario ya está asignado a otra organización: "This questionnaire is already assigned to "{{organization}}". A copy of the questionnaire will be created and the copy will be assigned instead." Al crear se copia primero.
- **Registration:** configura la diapositiva de login.

  | Campo | Visible por defecto | Requerido por defecto |
  |---|---|---|
  | Full name | Sí (fijo) | Sí (fijo) |
  | Email | Sí | Sí |
  | Phone | No | No |
  | Role | No | No |
  | Area | No | No |

  - Email o teléfono debe ser visible y requerido: "At least one of email or phone must be required".
  - Se construye como pregunta con tema `user-capture-data`.
- **Save:**
  - Envía `max_follow_ups: 2`. `due_date` solo en follow-up (null la borra al editar). `type` nunca se envía al editar.
  - Carrera detectada con `409 QUESTIONNAIRE_ALREADY_ASSIGNED`: "This questionnaire was just assigned to another organization. Please review and try again."
  - Final: "Assignation created!" / "Assignation updated!", enlace para compartir con Copy y "Go to Assignations".

**`/assignations/:id` — tipo default:**

- **Cabecera:** nombre; "org · X of N people · N completed · N pending" (con "+" mientras falten páginas por cargar); insignia; audiencia; Copy link; Export CSV; Export to Google Sheets.
- **Respondentes:** scroll infinito de 20 en 20 con "Load more".
- **Búsqueda** por nombre o email sobre lo cargado. Pista: "Search only covers the respondents loaded so far. Load more to search the rest."
- **Secciones:** Completed y Pending.
- **Columnas:** Name, Email, Attempts, Status (pill de cadena) y "View answers". El historial de intentos se expande por miembro.
- **CSV:** columnas Name, Email, Attempts, Status. Carga todas las páginas antes. Archivo `{nombre}.csv`.
- **Sheets:** las sesiones del cuestionario filtradas por la asignación.

**`/assignations/:id` — tipo follow-up:**

- **Cabecera:** insignia Follow-up, audiencia, estado de revisión (In review / Changes requested / Approved), vencimiento y Copy link.
- **Acción principal:** "Send reminder" mientras no esté completo; "Send for correction" cuando está completo y hay estado de revisión.
- **Pregunta actual:** "On question {{n}} of {{total}}" / "Nobody has opened the follow-up yet" / "Every question is answered".
- **Tabla de la sesión compartida:** #, Question, Answer, Answered, Review, con "View answer".
- **Diálogo de revisión:**
  - Muestra la respuesta, un comentario (≤ 1000, opcional), Reject / Approve y flechas anterior/siguiente.
  - Tras decidir, salta a la siguiente sin revisar. Al acabar: "Every answer of this attempt is reviewed".
  - Solo se puede revisar el intento actual, cuando está completo y con permiso de escritura.
- **Estados por respuesta:** Not reviewed / Approved / Rejected / "Approved before" (bloqueada en un intento anterior).
- **Send for correction:**
  - Solo con `review_status = changes_requested`.
  - El diálogo lista las respuestas rechazadas con sus comentarios.
  - Toast "Attempt N sent to M recipients". Con `502 RETRY_EMAIL_NOT_SENT` se recarga igual, porque el intento ya existe.
- **Selector de intento** en la URL (`?attempt=N`). Los intentos anteriores son de solo lectura.
- **Avisos de siguiente paso:**
  - "You rejected N answers — …can't correct them until you click Send for correction"
  - "Review every answer … · N left"
- **Tarjeta de miembros:** "Who can carry it on (N)".

### 10.12 Proyectos

**`/projects`:**

- **Pestañas:** All, To review, In progress, In correction, Overdue, Completed.
- **Búsqueda** con debounce de 300 ms. 10 por página, los más nuevos primero.
- **Columnas:**
  - Proyecto: chevron, iniciales de la organización, nombre, organización y "created {{date}}".
  - Estado: Not started / In progress / Needs your review / In correction / Completed / Overdue / No assignations.
  - Asignaciones: "{{approved}} of {{total}} approved" con barra.
  - Deadline con los niveles de urgencia.
  - Siguiente paso: Review answers / Open overdue / See correction / See progress / See results / Add assignations.
  - Menú ⋯: Edit, Delete.
- **Fila expandida:** Assignation, Answers ("Question 4 of 8" / "X of N questions"), Review ("R of T reviewed", "N sent back to the client"), Status y Review/Open.
- **Leyenda** de estados.
- **Borrar:** "Delete this project?" / "…Its assignations and their answers are kept; they just stop belonging to a project."
- **Editar (diálogo):**
  - Nombre requerido ≤ 200.
  - Organización de solo lectura.
  - Descripción ≤ 2000.
  - Deadline requerido; se puede mover pero no borrar ("Choose a deadline for the project" / "Enter a valid deadline").
  - Asignaciones disponibles = follow-ups de la organización que no están en otro proyecto.
- "New project" requiere la feature `assignations` incluida en el plan.

**`/projects/new`:** asistente de 3 pasos; no se guarda nada hasta "Create".

1. **Preguntas:** chat en modo `draft` (aprobar el borrador guarda el cuestionario) o elegir un cuestionario existente.
2. **Organización:** elegir o crear una (diálogo). Audiencia. El nombre de la asignación por defecto es "{org}: {title}".
3. **Proyecto:** elegir uno de la organización o crear uno (nombre por defecto = título del cuestionario; deadline requerido).

- Panel de resumen: "What we're going to create".
- **Al crear, en orden**, recordando cada id para que un reintento no duplique nada:
  1. Organización nueva, si aplica.
  2. Cuestionario (slug = `slugify(title)` + 6 hex, ≤ 100). Se copia si ya está asignado a otra organización.
  3. Asignación follow-up con `max_follow_ups: 2` y el registro por defecto.
  4. Crear el proyecto, o actualizarlo con sus asignaciones + la nueva.
- Toast "Done: questionnaire, assignation and project created".

### 10.13 Personalización (`/customization`, guardar requiere la feature `styles`)

**Campos:**

- Website URL. Pista: "Don't worry — we'll fetch the styles from your website automatically…".
- Logo URL con vista previa.
- Fuente, una de 6: Inter, Roboto, Poppins, Montserrat, Playfair Display, Lora.
- Color de marca (`#RRGGBB`). De él se derivan:
  - fondo del botón primario;
  - hover 12 % más oscuro;
  - texto legible: `#0F172A` si la luminancia es > 0.6, si no blanco;
  - color de enlace;
  - borde de foco de los inputs.

**Comportamiento:**

- Vista previa en vivo de lo que ve el respondente.
- **Reset:** solo restaura los valores por defecto en local. Toast "Reset to default values".
- **Guardar:** job de estilos con polling cada 5 s hasta 2 min.
  - Si cambió el sitio web, solo se envía `{website}`.
  - Si no, `{website, styles}`.
  - Luego se recarga el perfil. Toast "Styles updated successfully".

### 10.14 Perfil (`/profile`)

**Pestaña "Plan & usage":**

- Nombre del plan y Active/Expired.
- Barras de uso: "Questionnaires (all types)", "Responses" y una por feature. Límite negativo = "Unlimited". Ámbar desde el 75 % y rojo desde el 90 %.
- "Change plan" / "Choose a plan" → `/profile/plans`.
- "Manage billing" (solo si hay suscripción) → portal de la pasarela en la misma pestaña.

**Pestaña "Settings"** (requiere la feature `profile` y escritura):

- Idioma de la cuenta (`es-CO` / `en-US`). Afecta correos y pantallas del respondente, **no** la UI de la consola.
- "Maximum files per question": entero 1–20, default 10. Error "Enter a whole number from 1 to 20."; Save deshabilitado mientras sea inválido.
- Tracking (cada uno ≤ 64): Meta Pixel ID; LinkedIn Partner ID y Conversion ID; Google Ads Conversion ID (AW-…) y etiqueta.
- **Envío:** solo los campos de tracking modificados (vacío → null), `max_files` solo si cambió y `language` siempre.

### 10.15 Planes (`/profile/plans`, nunca bloqueada)

**Tarjeta de plan:**

- Precio desde unidades menores con formato de moneda local. Sin precio: "Price on request".
- Límites: "Experiences" (`max_questionnaires`), "Responses" y cada feature.
- Botón: "Subscribe", "Upgrade" o "Switch to this plan" (precio ≤ actual). Un plan no comprable muestra "Get in touch", que abre el formulario de contacto.
- "Buy yearly" / "Switch to yearly" con "Save {{percent}}%", donde `percent = round((1 − anual/(mensual×12))×100)`.
- **Insignias:** "{{count}} days free" (si tiene derecho a prueba y `trial_days > 0`), "Current plan · Monthly/Yearly", "Next plan" con "Starts on …".
- **Notas en la tarjeta actual:** "Renews on …", "N days left · until …", "Ends on …", "Free trial until …", "{{value}} off until/forever".

**Checkout:**

- Sin plan: iniciar checkout y redirigir en la misma pestaña.
- Con plan: cambio de plan. Si la respuesta es `checkout`, redirigir; si es `changed`, mostrar el resultado.

**Confirmaciones:**

- Downgrade o volver de anual a mensual: "Switch to a smaller plan?" / "Go back to monthly billing?".
- Cancelar: "Cancel your subscription?" con "Keep my plan".

**Sin confirmación:** "Resume subscription" y "Keep my current plan" (revertir).

**Otros:**

- Retorno de la pasarela con `?checkout=success|cancel`: aviso, refresco de plan y uso, y se quita el parámetro.
- Pista: "Have a promo code? Apply it at checkout."
- **Contacto:** email válido y teléfono requerido. Éxito: "Request received / You'll be contacted soon."

### 10.16 Usuarios

**`/users`:**

- Columnas: User (iniciales y nombre), Email, Role (Admin / Read only) y Type (Owner = root, si no Member).
- Vacío: "No users yet. Invite your first team member."
- "New user" requiere escritura y la feature `users`.

**`/users/new`:**

- Campos: Full name\*, Email\*, Password\* (≥ 8, con medidor) y rol en tarjetas:
  - Admin: "Full access, including creating other users."
  - Read only: "Can view resources but cannot make changes." (por defecto)
- Se muestra la matriz de permisos de §4.2.
- Errores: `EMAIL_ALREADY_EXISTS`, `INVALID_ROLE`, `FORBIDDEN`, `VALIDATION_ERROR` y uno genérico.
- Toast "User {{name}} created" y vuelta a `/users`.
- Sin permiso: "Admins only".

### 10.17 Integraciones (`/integrations`, 3 pestañas)

**API keys** (requieren escritura y la feature `api` incluida; una cuota agotada no bloquea la gestión de claves):

- Filas: Name, Created, Expires ("Never"), Last used ("Never") y Revoke ("Revoke API key?").
- Crear: nombre (requerido, ≤ 100) y expiración 7 / 30 / 60 / 90 días o Never (default 7).
- La clave en claro se muestra **una sola vez**, con botón Copy.

**Webhooks** (feature `webhook` incluida):

- Filas: URL, evento ("Response completed"), método `POST`, Edit y Delete.
- La URL debe empezar con `https://`.
- Referencia de entrega con los headers y el payload de ejemplo (§7.14).

**API reference:** documentación estática de la API externa (§8.11) con ejemplos `curl` copiables.

### 10.18 Documentación (`/documentation`)

**Guides:**

- 15 guías bilingües estáticas en 7 temas: getting-started, questionnaires, organizations, projects, analytics, brand-integrations, account.
- Búsqueda sin mayúsculas ni acentos y filtro por tema.
- Tiempo de lectura a 200 palabras/min.
- Cada guía (`/documentation/guides/:guideId`) tiene anterior/siguiente, índice y capturas por idioma.

**Videos:** lista de `GET /videos?language=`, incrustados en modo de privacidad reforzada.

### 10.19 Rutas ocultas

`/products` (sin entrada en la barra lateral):

- Lista y CRUD del catálogo de productos.
- Tarjeta de la plataforma de e-commerce (Authorize, Install, "Sync Products Now").
- "Create Experience" crea un quiz funnel.

### 10.20 Asumir cliente (solo super-admin)

- **Selector** en la parte superior de la barra lateral:
  - Búsqueda con debounce de 300 ms y "Load more" (20 por página).
  - Muestra nombre y email.
  - La selección es explícita: clic, o Enter tras moverse con flechas.
- La elección se guarda en el navegador.
- **Mientras está activa:**
  - Todo request salvo `/admin/*` envía `X-Assume-Customer-Id`.
  - El usuario efectivo pasa a ser `Customer-Admin` + root.
  - Banner "Viewing as {{name}} ({{email}})" con "Stop assuming".
  - Se vacía toda la caché y el contenido se vuelve a montar en `/ai-experience`.
- Si la API responde `ASSUME_NOT_ALLOWED`, `CUSTOMER_NOT_FOUND` o `ASSUMED_CUSTOMER_NOT_FOUND`, se deja de asumir automáticamente.

### 10.21 Alertas de plan en la consola

- El uso (`GET /customer/usage`) se carga una vez por cuenta.
- **429 `PLAN_LIMIT_REACHED`:** toast ámbar con el texto de la razón y refresco del uso.
- **Banner de uso:**
  - Aparece cuando alguna fila llega al 50 % (`used/limit`, acotado a 100; límite 0 = 100 %).
  - Niveles 50 / 75 / 90 / 100; rojo desde 90.
  - Cerrarlo lo silencia hasta el siguiente nivel. No se persiste.
  - Texto: "You're using {{percent}}% of your plan." con "See all (N)" y "Upgrade".
- **Notificaciones:** los fallos de petición usan un único componente: ámbar para límites de plan, rojo para el resto, con el texto del código del backend primero ([Anexo B](#anexo-b--catálogo-de-códigos-de-error)). Las validaciones del lado del cliente usan toasts destructivos.

### 10.22 Fechas

- Un timestamp del backend sin zona se interpreta como UTC.
- Las fechas de calendario (`YYYY-MM-DD`) se interpretan como medianoche local.
- La preferencia Local/UTC se aplica a los listados.

---

## 11. Trabajos asíncronos y tareas programadas

| Worker / tarea | Disparador | Límite de tiempo | Notas |
|---|---|---|---|
| Worker genérico de jobs | Cola | 10 min | Evaluación, prompts, quiz funnel, scraping, LinkedIn, sesiones de ecommerce, chat |
| Worker de estilos | Cola | 5 min, sin reintentos | Necesita un navegador headless (~2 GB de RAM) |
| Worker de eventos de analítica | Cola | — | Envía eventos al servicio de uso/analítica |
| Despachador de webhooks | Bus de eventos (pub/sub) | 3 s de conexión, 5 s de lectura | Sin reintentos |
| Recordatorios | Diario a las 13:00 UTC | — | §7.13 |
| Triggers del proveedor de identidad | Antes del registro y antes de emitir el token | — | Vinculación de Google, alta automática en el primer login con Google, evento `UserSignedIn` y marca de última sesión (excluye clientes máquina) |

**Polling en los clientes:**

| Job | Intervalo | Límite |
|---|---|---|
| Turno de chat / job de chat en segundo plano | 2 s | 5 min |
| Quiz funnel, scraper | 5 s | 5 min |
| Estilos | 5 s | 2 min |
| Evaluación, prompt, finalización (respondente) | 5 s | 120 intentos (~10 min) |
| Prueba de onboarding | 5 s y luego 4 s | Sin límite |

Un job termina cuando su estado sale de `PENDING`/`PROCESSING`: `COMPLETED` es éxito; `FAILED` y `CANCELLED` son fallo. En la app del respondente, la **evaluación falla abierta**; prompt y finalización lanzan error.

---

## 12. Eventos de dominio

Se emiten al servicio de uso/analítica. Algunos llevan `tags.feature` para contabilizar el uso (§7.2):

`UserRootRegistered`, `UserCreated`, `UserSignedIn`, `ProfileEdited`, `QuestionnaireCreated`, `QuestionnaireSessionCreated`, `QuestionnaireSessionUpdated`, `QuestionnaireSessionCompleted`, `AnalyticsFetched`, `DashboardGenerated`, `OrganizationCreated`, `OrganizationDeleted`, `AssignationCreated`, `AssignationDeleted`, `ApiUsage`, `FeatureCreated`, `SubscriptionCreated`, `SubscriptionRenewed`, `SubscriptionCancelled`, `PlanChanged`, `TrialWillEnd`, `PaymentFailed`.

Además, el uso de estilos y de webhooks se contabiliza al completarse.

---

## 13. Integraciones externas (por capacidad)

### 13.1 Proveedor de identidad

- Usuario = email, verificado automáticamente.
- Política de contraseña: **mínimo 8 caracteres, sin exigir tipos de carácter**.
- Contraseña temporal válida 7 días. Recuperación solo por email verificado, con límite de intentos.
- Tokens: id y access de **24 h**; refresh de **30 días**.
- Login con email + contraseña y **federado con Google** (OAuth code + PKCE, alcances openid, email y profile).
- Atributos propios: `customer_id`, `root`. Grupos = roles.
- **Primer login con Google:**
  - Confirma al usuario automáticamente y crea la cuenta (`customer_id` nuevo, root, `Customer-Admin`, plan `starter`, `onboarding_completed=false`).
  - Idioma = el de Google, o `es-CO`.
  - Si ya existe una cuenta de contraseña con ese email, la vincula y pide reintentar el login (`EMAIL_LINKED_RETRY_LOGIN`).
- Existe un cliente de máquina para procesos internos; sus inicios de sesión no cuentan como sesiones de cliente.
- **Token de respondente de asignación:** firmado por la API con un secreto propio. Contiene `assignations_id`, `organization_user_id` y `session_id`. [DEUDA] No expira.

### 13.2 Pasarela de pagos

Suscripciones mensuales y anuales, checkout alojado, portal de facturación, programación de cambios (para downgrades), cupones y códigos promocionales, pruebas gratuitas y webhooks firmados. Reglas en §7.4.

### 13.3 Modelo de lenguaje (LLM)

Debe soportar **salida estructurada** (JSON con esquema). Usos:

| Uso | Modelo |
|---|---|
| Generar cuestionarios (quiz funnel, chain, LinkedIn, chat) | Configurable por `AppSetting` (el más capaz) |
| Recomendar productos, evaluar respuestas | Modelo rápido y económico |
| Elegir el dashboard, diseñar estilos, chat con herramientas | — |

Las instrucciones de sistema se leen de los system prompts editables (§7.20).

### 13.4 Transcripción de voz en tiempo real

- Transcripción en streaming desde el navegador con un token efímero (~1 min) emitido por la API.
- Transporte primario de baja latencia y transporte alternativo por URL configurable por cuenta (`transcription_url`); hoy el audio va en PCM16 a 24 kHz.
- Eventos de texto parcial y final.
- Idioma es o en.

### 13.5 Almacén de objetos

| Contenedor | Acceso | Uso |
|---|---|---|
| Archivos de respuestas | Privado. Subida y descarga firmadas (15 min). CORS para PUT/POST | Clave `{customer_id}/{session_id}/{question_id}/{md5}{ext}`; hasta 500 MB |
| Archivos de prompts / medios | Lectura pública | Textos de prompts de las cadenas |
| System prompts | Privado y versionado | §7.20 |

### 13.6 Correo transaccional

Remitente `SUPPORT_EMAIL`; plantillas HTML en es y en (§7.21).

### 13.7 Scraping

- Catálogos de tiendas web (1–30 productos por ejecución).
- Perfiles de LinkedIn.
- Navegador headless para extraer el CSS de la marca.

### 13.8 Servicio de uso/analítica (interno, dependencia)

Autenticado con API key. **Contrato mínimo:**

| Endpoint | Propósito |
|---|---|
| `POST /events` | Recibir eventos de dominio con `tags.feature` |
| `POST /payments` | Registrar pagos |
| `GET /customers/{id}/usage` | Contadores del periodo |
| `PUT /customers/{id}/usage` | Ajustar contadores (fusión) |
| `GET /analytics/{qid}/general` | Analítica general del cuestionario |
| `GET /questionnaire/{qid}/data` | Datos agregados del dashboard (forma en §10.9) |

- Staging y producción comparten base de datos y se distinguen por `source`.
- La migración PUEDE absorber este servicio dentro de la API si mantiene la misma semántica de contadores por periodo.

### 13.9 Exportación a hojas de cálculo (Google Sheets, desde la consola)

- OAuth en el navegador con el alcance mínimo para crear y editar archivos propios.
- Se busca una hoja existente marcada con la propiedad `skylineExportKey = questionnaireId|assignationId`. Si existe, se limpia y reescribe; si no, se crea.
- Título "Answers - {title}".
- Columnas: Started At, User, Email, Phone (si hay), luego una por pregunta en orden.
- Valores: "Skipped", "File uploaded" / "N files uploaded", etiquetas de opción, vacío.
- Se abre en una pestaña nueva.

### 13.10 Plataforma de e-commerce (Shopify)

- OAuth con alcance de solo lectura de productos. Tokens que expiran con refresh.
- Sincronización de productos (precio de la primera variante).
- Webhooks de cumplimiento GDPR con HMAC.
- App embebida en el tema de la tienda: usa `GET /questionnaire/find?url=` para resolver qué cuestionario mostrar.

### 13.11 Otros

| Integración | Uso |
|---|---|
| Seguimiento de errores | Backend y ambos frontends (incluye PII en el backend) |
| Analítica web, mapas de calor, píxel de TikTok (consola), píxeles de cliente (respondente) | Marketing y medición |
| YouTube | Videos de documentación |
| Proveedor de fuentes web | Tipografías de marca |

---

## 14. Requisitos no funcionales

### 14.1 Rendimiento y límites

- Peticiones síncronas ≤ 29 s. Funciones normales ≤ 45 s; enviar, crear y editar cuestionarios hasta 120 s. Todo lo lento se hace con jobs.
- Timeout de cliente de 30 s en la app del respondente (el timeout se reporta como `status 0, code TIMEOUT`).
- **DEBERÍA** paginarse en la base de datos. Hoy muchos listados se cargan completos y se paginan en memoria ([DEUDA] de escalabilidad).

### 14.2 Seguridad

- Aislamiento por tenant en **todas** las lecturas y escrituras (ver las excepciones [DEUDA] en §15).
- Las API keys se guardan solo como hash SHA-256.
- Webhooks salientes firmados con HMAC-SHA256.
- Webhooks entrantes verificados: firma de la pasarela y HMAC de la plataforma de e-commerce.
- URLs firmadas de corta duración (15 min).
- No revelar existencia de cuentas en la recuperación de contraseña.
- El prompt del cliente en las cadenas se trata como dato no confiable (defensa contra inyección de prompts).
- Nunca devolver `payload` de jobs ni la configuración de puntaje al respondente.
- Sanitizar todo CSS y HTML proveniente de estilos, productos o LLM.
- CORS abierto hoy. **DEBERÍA** restringirse a los orígenes de las apps, el widget de tienda y los integradores.
- Sin límite de tasa propio hoy (solo cuotas de plan y el throttling del proveedor de identidad). **DEBERÍA** añadirse en los endpoints públicos (§15).

### 14.3 Disponibilidad y datos

- Persistencia con respaldo continuo (recuperación a un punto en el tiempo) y protección contra borrado en las tablas principales.
- Idempotencia en los webhooks de pagos y en la creación compuesta de proyectos del lado del cliente.

### 14.4 Internacionalización

- UI en **es** y **en**; respaldo `es`. Todo texto externalizado; nunca fijo en el código.
- Idioma de la cuenta (`es-CO` / `en-US`) separado del idioma de la UI de la consola. Controla correos, contenido generado y el idioma inicial del respondente.
- Tolerar textos en español más largos.
- Desactivar la traducción automática del navegador en ambas apps.

### 14.5 Accesibilidad

- **WCAG 2.1 AA.**
- Contraste AA (el violeta sobre blanco tiene 5.3:1).
- Operable por teclado con foco visible. Todos los controles etiquetados.
- Diálogos modales accesibles. Barras de progreso con valores. Errores anunciados; paneles de estado con región viva.
- Respetar `prefers-reduced-motion`.
- **El color nunca es la única señal:** fuerza de contraseña con etiqueta; niveles con nombre; errores con texto.
- `<html lang>` sincronizado con el idioma.

### 14.6 Responsive

- App del respondente mobile-first:
  - Variantes para pantallas bajas (≤ 740 px de alto).
  - El contenido se reajusta al abrir el teclado móvil y el campo activo queda a la vista.
  - Arrastre táctil completo en ranking.
- Consola: la preview de creación pasa a panel lateral por debajo de 1100 px.

### 14.7 Observabilidad

- Logs estructurados con nivel configurable.
- Seguimiento de errores en las tres piezas, con trazas y repetición de sesión en los frontends (10 % de sesiones y 100 % con error).
- `GET /health` con chequeos.

### 14.8 SEO (app del respondente)

- Meta description y keywords, robots "index, follow", Open Graph y tarjeta grande de Twitter.
- Título base configurable.

### 14.9 Entornos

| Entorno | API | App del respondente | Consola |
|---|---|---|---|
| dev | api.rev-ops.ai | rev-ops.ai | app.rev-ops.ai |
| staging | api.qa-questionaire.com | qa-questionaire.com | app.qa-questionaire.com |
| prod | api.questionaire.shop | q.getmappi.com | app.getmappi.com |

Despliegue continuo por rama (`ai-develop`, `staging`, `master`). Pruebas automáticas en cada pull request.

---

## 15. Deuda técnica y decisiones pendientes

Paridad 1:1 significa que la migración conoce todos estos puntos. Para cada uno, se DEBE decidir **replicar** o **corregir**.

| # | Tema | Situación actual | Recomendación |
|---|---|---|---|
| D1 | `PUT` y `DELETE /organizations/{id}` | No comprueban que la organización sea de la cuenta | **Corregir:** exigir Own |
| D2 | Borrar una organización | Deja miembros huérfanos | **Corregir:** borrado en cascada o bloqueo si tiene asignaciones |
| D3 | `POST /assignations/{id}/retries` | La propiedad no está verificada explícitamente | **Corregir:** exigir dueño o `Admin` |
| D4 | Endpoints públicos sensibles | `GET /customer/{id}/products`, `/settings`, `/styles`, `/jobs/{id}`, `/signed-urls` (acepta cualquier `customer_id`), generación por LinkedIn y por prompt | Mantener públicos los que el respondente necesita, pero limitar `/signed-urls` a sesiones válidas y añadir límite de tasa |
| D5 | OAuth de la plataforma de e-commerce | `state = customer_id`, sin nonce anti-falsificación | **Corregir:** nonce firmado |
| D6 | Token de respondente de asignación | No expira | **Corregir:** expiración razonable (p. ej. 30 días) + renovación |
| D7 | Envío con token de asignación inválido | Responde 200 sin procesar | **Corregir:** 401 |
| D8 | Lógica específica de clientes | `livingood`, Samurai8 (`mateo` / id fijo), dueño de LinkedIn `XhEFtqTt`, título de `3zWj6Nrg`, destinatarios del email de ventas, id de cuestionario por defecto | **Convertir en configuración** (tipos de resultado configurables y ajustes globales) |
| D9 | Login antiguo `/login` | Credenciales fijas; 500 con credenciales incorrectas | Eliminar o dejar solo en entornos no productivos |
| D10 | `POST /profile/customization` y trigger de migración de usuarios | Declarados en la infraestructura, sin código | No migrar |
| D11 | HTML de productos | Se renderiza sin sanear | **Corregir:** sanear |
| D12 | Tutorial de audio | Sin opción para saltarlo | **Corregir:** permitir continuar sin audio (o escribir) |
| D13 | Validación de email | Regex distintas entre login de asignación, captura y contacto | **Unificar** |
| D14 | IDs de analítica y píxel | Fijos en el HTML | Mover a configuración |
| D15 | Onboarding paso 2 | El workspace no se guarda | Guardar (nombre, idioma, sitio) o quitar el paso |
| D16 | Búsqueda del listado de cuestionarios | Solo del lado del cliente | Usar el `search` del servidor |
| D17 | Paginación en memoria | Listados completos en memoria | Paginar en la base de datos |
| D18 | Suplantación | Sin auditoría | Registrar las acciones hechas asumiendo |
| D19 | Webhooks salientes | Sin reintentos | Reintentos con backoff y registro de entregas |
| D20 | Correo de bienvenida | Plantillas sin uso | Enviar al registrarse o eliminar |
| D21 | Enlaces Privacy / Terms / Support en el login | No llevan a nada | Enlazar |
| D22 | Código muerto | Páginas antiguas de landing/home y servicios sin uso en la app del respondente | No migrar |
| D23 | Textos fijos en español | Prefijos de copia, email de ventas, contenido Samurai8, plantillas de onboarding | Externalizar a i18n |
| D24 | CORS `*` y ausencia de límite de tasa | — | Restringir y limitar |
| D25 | `VITE_RESULT_LAYOUT_V2` | Flag de la consola para ordenar libremente los bloques de resultado; el backend no lo soporta aún | Fuera de alcance, o diseñarlo en la migración |

---

## 16. Plan de migración y criterios de aceptación

### 16.1 Compatibilidad hacia atrás (DEBE)

1. Las URLs públicas `/q/{id}`, `/f/{id|slug}`, `/a/{id}`, `/session/{id}/results`, `/:id` (antigua) y `/privacy` siguen funcionando.
2. La API externa `/external/*` mantiene rutas, `X-API-Key`, formato de clave `QAIRE-…` y forma de respuesta. Las claves existentes siguen siendo válidas (se migra el hash).
3. Los webhooks salientes mantienen el cuerpo, los headers y el algoritmo de firma. El secreto de firma se conserva o se rota con aviso.
4. El widget de tienda sigue resolviendo `GET /questionnaire/find?url=`.
5. Los slugs y los ids de flujos, cuestionarios, sesiones y asignaciones se conservan.
6. Las claves de archivos de respuestas se conservan (o se migra el almacén conservando la estructura de claves).
7. Los usuarios pueden entrar con su contraseña actual. Si cambia el proveedor de identidad, hace falta una migración perezosa (validar contra el proveedor anterior en el primer login) o un restablecimiento forzado comunicado.
8. Las suscripciones en la pasarela de pagos siguen asociadas a su cuenta (`stripe_customer_id` y `stripe_subscription_id`).
9. La conexión con la plataforma de e-commerce se conserva (tokens migrados).

### 16.2 Migración de datos

Por entidad de §6: extraer, transformar (si cambia el esquema), cargar y verificar conteos y sumas de control.

**Orden sugerido:**

1. Catálogo: features, plans, videos, app settings, system prompts con historial.
2. Cuentas y usuarios.
3. Estilos.
4. Productos.
5. Cuestionarios, flujos, diagnósticos y prompts.
6. Organizaciones y miembros.
7. Asignaciones, respuestas de asignación y proyectos.
8. Sesiones y resultados de sesión.
9. Dashboards.
10. API keys y webhooks.
11. Jobs: opcional; solo los recientes.
12. Archivos del almacén de objetos.

### 16.3 Criterios de aceptación de paridad

Una prueba automatizada o manual documentada por cada punto:

1. **Registro:** con email y con Google (incluida la vinculación con una cuenta existente) → cuenta con plan `starter` de 1 mes y onboarding pendiente.
2. **Onboarding completo** de 7 pasos → cuestionario publicado y respuesta de prueba detectada.
3. **Crear cada tipo** (Regular, Diagnostic, Chaining, Quiz Funnel por web y por e-commerce, chat) → aparece en el listado y su enlace público funciona.
4. **Diagnóstico:** el puntaje de una sesión coincide exactamente con §7.7 (casos con checkbox, ranking, range, categorías, tiers límite y recomendaciones heredadas del tier inferior).
5. **Cadena de 3 etapas** con diagnóstico final generado → puntaje combinado de todas las etapas.
6. **Respondente:**
   - reanudar tras recargar;
   - disclaimer;
   - landing;
   - validaciones de cada control;
   - checkbox exclusivo;
   - slider que no cuenta hasta tocarlo;
   - Skip solo en opcionales;
   - archivos (límite, pegar, reintento);
   - audio con transcripción;
   - evaluación por IA con reintentos y falla abierta;
   - captura de datos;
   - resultados recargables por URL;
   - PDF.
7. **Asignación default:** login por email/teléfono, errores `USER_NOT_FOUND` y `NOT_IN_AUDIENCE`, progreso por respondente, exportaciones CSV y Sheets.
8. **Asignación follow-up:**
   - sesión compartida entre dos miembros (fusión sin sobrescribir con vacíos);
   - completar;
   - revisar (aprobar y rechazar);
   - enviar a corrección → intento 2 con respuestas bloqueadas y rechazadas vacías;
   - pantallas de completado por `review_status`.
9. **Recordatorios:** job diario y botón manual; asuntos según los días restantes; sin duplicados en el mismo día UTC.
10. **Proyectos:** estados y porcentajes según §7.12, incluido el cálculo de vencido en UTC−12.
11. **Gate de plan:** cada razón de rechazo produce su 429 y su texto en la UI; `Admin` pasa; los contadores suman según §7.2; el banner de uso aparece en los umbrales.
12. **Facturación:** checkout, upgrade inmediato con prorrateo, downgrade programado y su reversión, cancelar y reanudar, portal, cupones, prueba una sola vez por cuenta, reinicio de contadores al subir de plan.
13. **Dashboard:** generación única, bloqueo sin `dashboards`, fórmulas de embudo y NPS.
14. **API externa y webhooks:** firma verificable por un receptor de prueba; clave revocada → 401.
15. **Suplantación:** header respetado, 403 para quien no es `Admin`, banner y salida automática ante los errores.
16. **Super-admin:** CRUD de features, plans, coupons, videos y system prompts (con validación de placeholders y versiones); ajuste de plan y uso de una cuenta.
17. **i18n:** todas las pantallas en es y en; los correos en el idioma de la cuenta.
18. **Accesibilidad:** auditoría AA automatizada sin errores críticos en las pantallas principales.

---

## Anexo A — Marca y diseño

### A.1 Consola (Mappi)

**Personalidad:** segura, clara y amable. Tono sencillo y tranquilizador, nunca exagerado: dice el resultado y se aparta.

**Paleta:**

| Token | Valor |
|---|---|
| Primario (acento único) | `#8249df` (HSL 263 70% 58%) |
| Hover | `#6c3aed` |
| Tinte suave | `#f0e8fc` |
| Barra lateral | `#13111d` (casi negro) |
| Resaltado "AI Experience" | `#06b6d4` (cian) |
| Neutros | Fríos y claros |

**Tipografía:** Inter para la interfaz; Fraunces (serif) solo en los títulos grandes (selector de tipo, pantalla de éxito).

**Componentes:** botones en píldora de 40 px de alto; tarjetas blancas con radio de 14 px y borde fino.

**Tema:** solo claro. Existe CSS de modo oscuro pero no se activa.

**Referencia visual:** las pantallas de creación. Un solo contenedor para todos los tipos, editor centrado a 880 px y vista previa en vivo de lo que ve el respondente.

**Principios:**

1. **Claridad antes que decoración:** una tarea principal por pantalla.
2. **Confianza tranquila:** un solo acento violeta, espacio generoso.
3. **Mostrar el resultado, no la maquinaria.**
4. **La calidez es una función:** texto humano, serif en los momentos grandes y la vista previa en vivo.
5. **Velocidad y confianza:** buenos valores por defecto, formularios tolerantes, estados vacíos y de error honestos.

El acento se usa solo en lo que importa: el botón principal, el paso actual y la tarjeta seleccionada. Toda acción deshabilitada dice por qué.

**Anti-referencias:**

- Admin genérico estilo Material/Bootstrap.
- Estética SaaS de gradientes: gradientes, texto con gradiente, tarjetas de métricas enormes.
- Cristal oscuro con neón.
- Enterprise saturado (barras de herramientas densas, texto diminuto).

### A.2 App del respondente (tema por defecto, sin marca del cliente)

| Token | Valor |
|---|---|
| Fondo | Blanco roto cálido (HSL 40 30% 96%) |
| Texto | zinc-900 |
| Tarjetas | Blanco |
| Primario | Casi negro (zinc-900) |
| Acento | Terracota `#C45A3D` |
| Éxito | HSL 142 76% 36% |
| Advertencia | HSL 38 92% 50% |
| Radios | 0.5rem; botones en píldora; tarjetas de 1rem |
| Fuentes | Montserrat para texto; serif para títulos |

- Logos y favicon propios.
- **La marca del cliente sobrescribe estos tokens** (§9.15).

### A.3 Nombres históricos

"QuestionAIre" (monograma "Q", lema "make the shopping experience a total breeze"), dominios `questionaire.shop` y `getmappi.com`, contacto `info@questionaire.shop`.

El producto nuevo se presenta como **Mappi**. Se DEBE decidir si la app del respondente pasa a mostrar "Mappi" en lugar de "QuestionAIre".

---

## Anexo B — Catálogo de códigos de error

| Código | HTTP | Contexto |
|---|---|---|
| `INVALID_JSON` | 400 | Cuerpo que no es JSON |
| `VALIDATION_ERROR` | 400 | Validación de campos |
| `INVALID_REQUEST` | 400 | Falta un parámetro |
| `INVALID_UUID` | 400 | Id mal formado |
| `UNAUTHORIZED` | 401 | Sin autenticación válida |
| `FORBIDDEN` | 403 | Sin privilegios o propiedad |
| `ASSUME_NOT_ALLOWED` | 403 | Suplantación sin ser `Admin` |
| `ASSUMED_CUSTOMER_NOT_FOUND` | 404 | Suplantación de una cuenta inexistente |
| `PLAN_LIMIT_REACHED` | 429 | Con `details.reason` ∈ `NO_PLAN`, `PLAN_INACTIVE`, `PLAN_NOT_FOUND`, `FEATURE_NOT_IN_PLAN`, `FEATURE_LIMIT_REACHED`, `RESPONSE_LIMIT_REACHED`, `QUESTIONNAIRE_LIMIT_REACHED` |
| `USAGE_UNAVAILABLE` | 503 | Falla el servicio de uso |
| `EMAIL_ALREADY_EXISTS` | 409 | Registro o alta de usuario |
| `INVALID_ROLE` | 400 | Alta de usuario |
| `TOO_MANY_ATTEMPTS` | 429 | Recuperación de contraseña |
| `INVALID_RESET_CODE`, `EXPIRED_RESET_CODE`, `INVALID_PASSWORD` | 400 | Recuperación de contraseña |
| `CUSTOMER_NOT_FOUND` | 404 | Cuenta |
| `PLAN_NOT_FOUND` | 404 | Plan |
| `PLAN_NOT_PURCHASABLE`, `SAME_PLAN`, `NO_SUBSCRIPTION`, `NO_SCHEDULED_CHANGE`, `NO_STRIPE_CUSTOMER` | 400 | Facturación |
| `STRIPE_UNAVAILABLE` | 502 | Falla la pasarela |
| `EMAIL_UNAVAILABLE` | 502 | Falla el correo de contacto |
| `INVALID_TYPE`, `INVALID_SORT`, `INVALID_ORDER`, `INVALID_IS_ACTIVE` | 400 | Listado de cuestionarios |
| `QUESTIONNAIRE_NOT_FOUND` | 404 | |
| `QUESTIONNAIRE_ALREADY_ANSWERED` | 409 | Edición bloqueada |
| `SLUG_ALREADY_IN_USE` | 409 | |
| `FLOW_NOT_FOUND` | 404 | |
| `SESSION_NOT_FOUND`, `SESSION_RESULTS_NOT_FOUND` | 404 | |
| `DASHBOARD_GENERATION_FAILED`, `ANALYTICS_UNAVAILABLE` | 502 | |
| `JOB_NOT_FOUND` | 404 | |
| `INVALID_JOB_DATA` | 500 | |
| `SHOPIFY_NOT_CONNECTED`, `SHOPIFY_TOKEN_EXPIRED`, `TOKEN_EXCHANGE_FAILED` | 400 | E-commerce |
| `DOMAIN_EMAIL_CONFLICT` | 409 | Organización |
| `ORGANIZATION_NOT_FOUND` | 404 | |
| `INTERNAL_ERROR` | 500 | |
| `QUESTIONNAIRE_ALREADY_ASSIGNED` | 409 | Asignación |
| `AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`, `ASSIGNATION_IN_PROJECT` | 400 | Asignación |
| `ASSIGNATION_NOT_FOUND` | 404 | |
| `MISSING_IDENTIFIER` | 400 | Login de respondente |
| `USER_NOT_FOUND`, `NOT_IN_AUDIENCE` | 403 | Login de respondente |
| `FOLLOW_UP_COMPLETED` | 409 | Follow-up cerrado |
| `NOT_A_FOLLOW_UP` | 400 | |
| `NO_RECIPIENTS` | 422 | Recordatorio |
| `REMINDER_NOT_SENT` | 502 | Recordatorio |
| `FOLLOW_UP_NOT_COMPLETED` | 409 | Revisión |
| `QUESTION_NOT_FOUND` | 404 | Revisión |
| `QUESTION_LOCKED` | 400 | Revisión |
| `REVIEW_INCOMPLETE` | 409 | Reintento |
| `NOTHING_TO_RETRY` | 400 | Reintento |
| `RETRY_EMAIL_NOT_SENT` | 502 | Reintento (el intento ya se creó) |
| `INVALID_PAGE_SIZE`, `INVALID_CURSOR` | 400 | Respondentes |
| `INVALID_PROJECT_STATUS` | 400 | |
| `PROJECT_NOT_FOUND` | 404 | |
| `ASSIGNATION_ORGANIZATION_MISMATCH`, `ASSIGNATION_NOT_FOLLOW_UP` | 400 | Proyecto |
| `ASSIGNATION_IN_OTHER_PROJECT` | 409 | Proyecto |
| `API_KEY_NOT_FOUND`, `WEBHOOK_NOT_FOUND` | 404 | |
| `INVALID_API_KEY` | 401 | API externa |
| `INVALID_LANGUAGE` | 400 | Videos |
| `UNKNOWN_PLAN`, `INVALID_DATE_RANGE`, `UNKNOWN_FEATURE` | 400 | Admin |
| `FEATURE_ALREADY_EXISTS`, `PLAN_ALREADY_EXISTS`, `COUPON_CODE_TAKEN` | 409 | Admin |
| `INVALID_NAME`, `INVALID_STRIPE_PRICE`, `INVALID_COUPON`, `COUPON_CURRENCY_MISMATCH`, `COUPON_INTERVAL_NEEDS_OWN_PRODUCT` | 400 | Admin |
| `FEATURE_NOT_FOUND`, `COUPON_NOT_FOUND`, `UNKNOWN_PROMPT` | 404 | Admin |
| `INVALID_PLACEHOLDERS` | 400 | System prompts |

**Códigos con texto propio en la consola:** `SLUG_ALREADY_IN_USE`, `SHOPIFY_NOT_CONNECTED`, `SHOPIFY_TOKEN_EXPIRED`, `FOLLOW_UP_COMPLETED`, `NO_RECIPIENTS`, `ASSUME_NOT_ALLOWED`, `ASSUMED_CUSTOMER_NOT_FOUND`, `ASSIGNATION_IN_OTHER_PROJECT`, `ASSIGNATION_ORGANIZATION_MISMATCH`, `ASSIGNATION_NOT_FOLLOW_UP`, `PROJECT_NOT_FOUND`, `ASSIGNATION_IN_PROJECT`, `AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`, `FOLLOW_UP_NOT_COMPLETED`, `REVIEW_INCOMPLETE`, `NOTHING_TO_RETRY`, `QUESTION_LOCKED`, `RETRY_EMAIL_NOT_SENT`, `QUESTION_NOT_FOUND`, más las siete razones de límite de plan.

---

## Anexo C — Variables de configuración (por propósito)

Los nombres son orientativos. Lo que se exige es la **capacidad** de configurar cada cosa por entorno. Los secretos van en un gestor de secretos, nunca en el código.

**API:**

| Grupo | Variables |
|---|---|
| URLs | API pública, app del respondente (`FRONTEND_URL`), consola (`ADMIN_FRONTEND_URL`) |
| Persistencia | Conexión o nombres de colecciones de cada entidad de §6 |
| Almacén de objetos | Contenedores de archivos de respuestas, prompts/medios y system prompts |
| Colas y bus | Cola de jobs, cola de estilos, cola de eventos de analítica, tópico de webhooks |
| Secretos | Firma de webhooks salientes; firma de tokens de respondente; API del LLM; cliente OAuth de Google; app de e-commerce (key + secret); pasarela de pagos (clave + secreto de webhook); scraper (token + URL base); servicio de uso/analítica (URL + API key); proveedor de identidad (pool/cliente); seguimiento de errores (DSN) |
| Otros | Modelo de LLM por defecto; remitente de correo (`SUPPORT_EMAIL`); nivel de log |

**App del respondente:** URL de la API; URL pública de la propia app; título base; ID de píxel de Meta por defecto; ID de analítica web; ID de mapas de calor; DSN de errores.

**Consola:** URL de la API; URL de la app del respondente; configuración del proveedor de identidad (pool, cliente, dominio de login federado); cliente OAuth para exportar a hojas de cálculo; DSN de errores; ID de mapas de calor; URL de instalación de la app de e-commerce; modo de login simulado para desarrollo; flag `RESULT_LAYOUT_V2`.

---

## Anexo D — Claves de system prompts

| Clave | Placeholders requeridos |
|---|---|
| `shared--basic-rules-to-create-a-questionnaire` | — |
| `quiz-funnel--rules-to-create-profiling-questionnaires` | — |
| `quiz-funnel--rules-to-create-product-questionnaires` | — |
| `quiz-funnel--rules-to-recommend-products` | — |
| `chat--conversation-rules` | — |
| `chat--rules-to-build-questionnaires` | — |
| `chain--rules-to-create-questionnaires` | `{admin_instructions}`, `{base_rules}` |
| `diagnostic--rules-to-create-diagnostics` | — |
| `linkedin--rules-to-create-diagnostic-questionnaires` | — |
| `followups--rules-to-evaluate-answers` | — |
| `styles--rules-to-extract-brand-styles` | — |
| `dashboards--select-dashboard-type` | `{dashboard_catalog}` |

El texto actual de cada clave (incluido el texto por defecto de la plataforma) DEBE exportarse del sistema actual y migrarse con su historial.

---

## Anexo E — Referencia de la implementación actual

**Solo informativo. No es un requisito.** Sirve para identificar qué hay que reemplazar si se cambia de proveedor.

| Capacidad | Implementación actual |
|---|---|
| API | Funciones serverless detrás de un API gateway (Python 3.10), en una región us-east-1 |
| Persistencia | Base de datos NoSQL clave-valor gestionada (DynamoDB), facturación bajo demanda, PITR |
| Colas / bus | Invocación asíncrona de funciones; tópico pub/sub para webhooks |
| Tarea programada | Cron gestionado `0 13 * * ? *` |
| Almacén de objetos | S3 |
| Identidad | AWS Cognito + Google federado (Hosted UI) |
| Correo | AWS SES con plantillas HTML |
| Pagos | Stripe (Checkout, Billing Portal, Subscription Schedules, Coupons/Promotion Codes) |
| LLM | OpenAI (modelos "gpt-5.5" para generación y "gpt-5-mini" para recomendar y evaluar) |
| Transcripción | OpenAI Realtime (`gpt-realtime-whisper`) por WebRTC o WebSocket con token efímero |
| Scraping | Apify (catálogo e-commerce y LinkedIn); Playwright/Chromium para estilos |
| E-commerce | Shopify Admin API |
| Hojas de cálculo | Google Drive + Sheets desde el navegador |
| Frontends | SPA React + TypeScript, alojadas en AWS Amplify |
| Errores | Sentry |
| Medición | Google Analytics, Meta Pixel, LinkedIn Insight, Google Ads, TikTok Pixel, Microsoft Clarity |
| Infraestructura como código | SAM + Terraform; CI por rama |
