#!/usr/bin/env python3
"""Creates the placeholder slices of item 0 (one per page and widget), each with its i18n files.

Run once from backend/: python3 tools/stub-slices.py. Existing slices are never overwritten; each owner item replaces
its placeholder with the real screen.
"""

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / 'assets'

# (app, layer, slice, Component, English title, Spanish title)
SLICES = [
    ('console', 'pages', 'sign-in', 'SignInPage', 'Signing you in', 'Iniciando sesión'),
    ('console', 'pages', 'forgot-password', 'ForgotPasswordPage', 'Forgot your password?', '¿Olvidaste tu contraseña?'),
    ('console', 'pages', 'reset-password', 'ResetPasswordPage', 'Reset your password', 'Restablece tu contraseña'),
    ('console', 'pages', 'onboarding', 'OnboardingPage', 'Welcome to Mappi', 'Bienvenido a Mappi'),
    ('console', 'pages', 'ai-experience', 'AiExperiencePage', 'What do you want to create today?', '¿Qué quieres crear hoy?'),
    ('console', 'pages', 'questionnaires', 'QuestionnairesPage', 'Questionnaires', 'Cuestionarios'),
    ('console', 'pages', 'questionnaire-new', 'QuestionnaireNewPage', 'What do you want to create?', '¿Qué quieres crear?'),
    ('console', 'pages', 'questionnaire-editor', 'QuestionnaireEditorPage', 'Questionnaire', 'Cuestionario'),
    ('console', 'pages', 'quiz-funnel-create', 'QuizFunnelCreatePage', 'Quiz Funnel', 'Quiz Funnel'),
    ('console', 'pages', 'questionnaire-answers', 'QuestionnaireAnswersPage', 'Questionnaire Answers', 'Respuestas del cuestionario'),
    ('console', 'pages', 'answer-detail', 'AnswerDetailPage', 'Response Details', 'Detalle de la respuesta'),
    ('console', 'pages', 'questionnaire-dashboard', 'QuestionnaireDashboardPage', 'Dashboard', 'Tablero'),
    ('console', 'pages', 'organizations', 'OrganizationsPage', 'Organizations', 'Organizaciones'),
    ('console', 'pages', 'organization-form', 'OrganizationFormPage', 'Organization', 'Organización'),
    ('console', 'pages', 'organization-view', 'OrganizationViewPage', 'Organization', 'Organización'),
    ('console', 'pages', 'assignations', 'AssignationsPage', 'Assignations', 'Asignaciones'),
    ('console', 'pages', 'assignation-form', 'AssignationFormPage', 'Assignation', 'Asignación'),
    ('console', 'pages', 'assignation-detail', 'AssignationDetailPage', 'Assignation', 'Asignación'),
    ('console', 'pages', 'projects', 'ProjectsPage', 'Projects', 'Proyectos'),
    ('console', 'pages', 'project-new', 'ProjectNewPage', 'New project', 'Nuevo proyecto'),
    ('console', 'pages', 'customization', 'CustomizationPage', 'Customization', 'Personalización'),
    ('console', 'pages', 'plans', 'PlansPage', 'Plans', 'Planes'),
    ('console', 'pages', 'users', 'UsersPage', 'Users', 'Usuarios'),
    ('console', 'pages', 'user-new', 'UserNewPage', 'New user', 'Nuevo usuario'),
    ('console', 'pages', 'integrations', 'IntegrationsPage', 'Integrations', 'Integraciones'),
    ('console', 'pages', 'documentation', 'DocumentationPage', 'Documentation', 'Documentación'),
    ('console', 'pages', 'documentation-guide', 'DocumentationGuidePage', 'Guide', 'Guía'),
    ('console', 'pages', 'products', 'ProductsPage', 'Products', 'Productos'),
    ('respondent', 'pages', 'questionnaire', 'QuestionnairePage', 'Questionnaire', 'Cuestionario'),
    ('respondent', 'pages', 'flow', 'FlowPage', 'Questionnaire', 'Cuestionario'),
    ('respondent', 'pages', 'results', 'ResultsPage', 'Results', 'Resultados'),
    ('respondent', 'pages', 'assignation', 'AssignationPage', 'Questionnaire', 'Cuestionario'),
    ('respondent', 'pages', 'privacy', 'PrivacyPage', 'Privacy Policy', 'Política de privacidad'),
]

WIDGETS = [
    # (app, slice, Component, what it renders until built)
    ('console', 'usage-banner', 'UsageBanner', 'null'),
    ('console', 'assume-customer', 'AssumeCustomer', 'null'),
    ('console', 'plan-usage-panel', 'PlanUsagePanel', 'placeholder'),
    ('console', 'account-settings', 'AccountSettings', 'placeholder'),
]


def write(path: Path, content: str) -> None:
    if path.exists():
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')


for app, layer, slug, component, en, es in SLICES:
    base = ROOT / app / layer / slug
    write(base / 'index.ts', f"export {{{component}}} from './ui/{component}';\n")
    write(base / 'ui' / f'{component}.tsx', (
        "import {Placeholder} from '@shared/ui';\n"
        "import {useTranslation} from 'react-i18next';\n\n"
        f"export function {component}() {{\n"
        f"  const {{t}} = useTranslation('{layer}.{slug}');\n"
        "  return <Placeholder title={t('title')} />;\n"
        "}\n"
    ))
    write(base / 'i18n' / 'en.json', json.dumps({'title': en}, ensure_ascii=False, indent=2) + '\n')
    write(base / 'i18n' / 'es.json', json.dumps({'title': es}, ensure_ascii=False, indent=2) + '\n')

for app, slug, component, mode in WIDGETS:
    base = ROOT / app / 'widgets' / slug
    write(base / 'index.ts', f"export {{{component}}} from './ui/{component}';\n")
    if mode == 'null':
        body = f"/** Placeholder until its item builds it. */\nexport function {component}() {{\n  return null;\n}}\n"
    else:
        body = (
            "import {Placeholder} from '@shared/ui';\n"
            "import {useTranslation} from 'react-i18next';\n\n"
            f"export function {component}() {{\n"
            f"  const {{t}} = useTranslation('widgets.{slug}');\n"
            "  return <Placeholder title={t('title')} />;\n"
            "}\n"
        )
        write(base / 'i18n' / 'en.json', json.dumps({'title': component}, indent=2) + '\n')
        write(base / 'i18n' / 'es.json', json.dumps({'title': component}, indent=2) + '\n')
    write(base / 'ui' / f'{component}.tsx', body)

print('stub slices written')
