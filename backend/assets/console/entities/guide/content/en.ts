import type {GuideId, GuideText} from '../model/types';

/** The 13 documentation guides in English (PRD §10.18). Same ids, order and topics as content/es.ts. */
export const GUIDES_EN: Record<GuideId, GuideText> = {
  'welcome': {
    title: 'Welcome to Mappi',
    summary:
      'A tour of the console: what each section of the sidebar is for and how a questionnaire goes from idea to results.',
    sections: [
      {
        id: 'what-is-mappi',
        heading: 'What Mappi does',
        paragraphs: [
          'Mappi turns questionnaires into actions. You design a questionnaire (often with the help of AI), send it to the people who should answer it, and Mappi turns their answers into results: a score and a level, a product recommendation, a profile or a simple thank-you message.',
          'Everything happens in the console. Respondents never need an account: they open a link, answer, and see their result.',
        ],
      },
      {
        id: 'sidebar',
        heading: 'The sidebar',
        paragraphs: [
          'The sidebar groups the console in three blocks. Design holds AI Experience (the home page), Design Experience, Questionnaires and Customization. Send and track holds Organizations, Assignations and Projects. Settings holds Users, Profile and this Documentation.',
          'At the bottom you will find the language selector, your account block, and Log out. The language selector only changes the language of the console; the language of the emails and of the respondent screens is your account language, in Profile.',
        ],
        screenshot: {
          name: 'ai-experience',
          alt: 'The console with the sidebar and the AI Experience chat',
        },
      },
      {
        id: 'the-journey',
        heading: 'From idea to results',
        paragraphs: ['A typical journey has four steps:'],
        steps: [
          'Create a questionnaire in AI Experience or from Questionnaires → New Questionnaire.',
          'Publish it and share its link, or assign it to the members of an organization.',
          'Follow the answers as they arrive in Answers.',
          'Read the dashboard to understand the results and act on them.',
        ],
      },
      {
        id: 'roles',
        heading: 'Read-only users',
        paragraphs: [
          'If your role is read-only you can see everything, but buttons that create or change something are disabled. Hover over a disabled button to read why.',
        ],
      },
    ],
  },
  'first-questionnaire': {
    title: 'Create your first questionnaire',
    summary:
      'Pick a type, write the details, add questions and decide what people see at the end — in three steps.',
    sections: [
      {
        id: 'choose-a-type',
        heading: 'Choose a type',
        paragraphs: [
          'Go to Questionnaires and click New Questionnaire. Mappi offers four types: Regular for classic surveys that end with a thank-you message, Diagnostic to score each respondent and place them in tiers, Quiz Funnel to recommend products from your store, and Chaining to generate a tailored questionnaire from the first answers and your prompts.',
        ],
        screenshot: {
          name: 'questionnaire-new',
          alt: 'The four questionnaire types to choose from',
        },
      },
      {
        id: 'details',
        heading: 'Step 1: details',
        paragraphs: [
          'Write a title (required) and, if you want, a custom link (slug): lowercase letters, numbers and hyphens. Leave the slug empty and Mappi generates it from the title. You can also turn on a landing page and a disclaimer the respondent must read first.',
        ],
      },
      {
        id: 'questions',
        heading: 'Step 2: questions',
        paragraphs: [
          'Add questions and group them by category. Each question has a title, an input type (single or multiple selection, select, ranking, text, audio, range, file or a message) and can be required. Drag questions to reorder them or to move them to another category.',
        ],
        tip: 'Text and audio questions can ask up to five follow-up questions when an answer does not meet your acceptance criteria.',
      },
      {
        id: 'ending',
        heading: 'Step 3: the ending',
        paragraphs: [
          'Decide what the respondent sees at the end: a thank-you message, a call to action with a button, or a form to capture their data. In a diagnostic you define the tiers instead, from 0 to the top score with no gaps.',
          'Nothing is saved until you click Create and confirm. Then you can copy the link, view the questionnaire or keep editing.',
        ],
      },
    ],
  },
  'create-with-ai': {
    title: 'Create a questionnaire with AI',
    summary:
      'Describe what you need in AI Experience and let Mappi draft, refine and create the questionnaire with you.',
    sections: [
      {
        id: 'start-a-chat',
        heading: 'Start a chat',
        paragraphs: [
          'AI Experience is the home page of the console. Write what you want to create — for example "a diagnostic of digital maturity for small shops, 8 questions" — and press Enter. Shift+Enter adds a line break.',
          'The assistant answers with a draft that appears in the live preview on the right, where you can answer the questions as a respondent would.',
        ],
        screenshot: {
          name: 'ai-experience',
          alt: 'AI Experience with the chat and the live preview',
        },
      },
      {
        id: 'refine',
        heading: 'Refine the draft',
        paragraphs: [
          'Ask for changes in plain words: add a question, change the tone, translate it, make the scale 1 to 10. Quick replies appear as buttons when the assistant offers options.',
        ],
        tip: 'A conversation holds up to 40 messages. Click New chat to start over.',
      },
      {
        id: 'create',
        heading: 'Create it',
        paragraphs: [
          'When you are happy with the draft, ask the assistant to create it. A "Questionnaire created" card appears with buttons to edit or view it. From there it is a normal questionnaire: you can edit it, share it and read its answers.',
          'The assistant can also do other tasks for you, such as listing your organizations or your assignations.',
        ],
      },
    ],
  },
  'edit-questionnaires': {
    title: 'Edit and manage questionnaires',
    summary:
      'Find, filter, edit, activate and copy questionnaires from the listing, and what "Locked" means.',
    sections: [
      {
        id: 'the-listing',
        heading: 'The listing',
        paragraphs: [
          'Questionnaires lists every questionnaire of your account. Search by title (⌘/Ctrl+K focuses the search box), filter by type and state, and sort by creation or update date. Each row shows the type, the number of questions and an Active toggle.',
        ],
        screenshot: {
          name: 'questionnaires',
          alt: 'The questionnaire listing with its filters',
        },
      },
      {
        id: 'activate',
        heading: 'Active and inactive',
        paragraphs: [
          'Only active questionnaires accept answers. Turn the toggle off to stop collecting responses without deleting anything; turn it on again at any time.',
        ],
      },
      {
        id: 'edit',
        heading: 'Edit',
        paragraphs: [
          'Click Edit to open the same three steps you used to create it. There is no autosave: click Save changes when you are done.',
        ],
      },
      {
        id: 'locked',
        heading: 'Locked questionnaires',
        paragraphs: [
          'Once a questionnaire has answers it is locked, so the answers keep matching the questions they were given for. To change it, click Create a copy: you get a new questionnaire with no answers, ready to edit.',
        ],
        tip: 'Copies keep the questions, the ending and the settings, with a new link.',
      },
    ],
  },
  'share-and-collect': {
    title: 'Share a questionnaire and collect answers',
    summary:
      'Publish a questionnaire, share its public link and follow every answer as it arrives.',
    sections: [
      {
        id: 'public-link',
        heading: 'The public link',
        paragraphs: [
          'Every questionnaire has a public link built from its slug. Copy it from the listing (Copy link) or from the success screen after creating it, and share it by email, on your website or on social media. Anyone with the link can answer while the questionnaire is active.',
        ],
      },
      {
        id: 'test',
        heading: 'Test it first',
        paragraphs: [
          'Open the link yourself before sharing it: answer it as a respondent and check the result screen. Your test answers appear in Answers like any other.',
        ],
      },
      {
        id: 'answers',
        heading: 'Follow the answers',
        paragraphs: [
          'Open Answers from the row of the questionnaire. Each session shows when it started, the name and email if the respondent left them, the progress and the state: Filling, Filled out, Processing or Completed. Click View to read every answer with the time spent on it and the result the person received.',
        ],
        screenshot: {name: 'answers', alt: 'The answers of a questionnaire'},
      },
      {
        id: 'files',
        heading: 'Files and audio',
        paragraphs: [
          'Images, PDFs, audio and video that respondents upload can be previewed in the answer detail, and any file can be downloaded.',
        ],
      },
    ],
  },
  'organizations-and-members': {
    title: 'Organizations and members',
    summary:
      'Keep the companies or teams you work with, with their members, and import members from a CSV file.',
    sections: [
      {
        id: 'what-for',
        heading: 'What organizations are for',
        paragraphs: [
          'An organization is a company, a client or a team whose members will answer your questionnaires. Assignations and projects are always made for one organization.',
        ],
        screenshot: {
          name: 'organizations',
          alt: 'The organization cards',
        },
      },
      {
        id: 'create',
        heading: 'Create an organization',
        paragraphs: [
          'Click New Organization and write its name. The email domain is optional: when you set it, Mappi warns you about members whose email uses another domain, but saves them anyway.',
        ],
      },
      {
        id: 'members',
        heading: 'Add members',
        paragraphs: [
          'Each member needs a name and at least an email or a phone; role and area are optional and let you send an assignation to a part of the organization only. Names are saved without accents and in lowercase so that searches always find them.',
        ],
      },
      {
        id: 'csv',
        heading: 'Import from CSV',
        paragraphs: [
          'Download the template, fill in one row per member and import it. The file can use commas or semicolons and the columns can be in English or Spanish (name/nombre, email/correo, phone/telefono, role/rol, area). Rows that cannot be imported are listed with the reason.',
        ],
        tip: 'Deleting an organization deletes its members. It is refused while assignations or projects still use it.',
      },
    ],
  },
  'assignations': {
    title: 'Send questionnaires with assignations',
    summary:
      'Assign a questionnaire to the members of an organization, choose who answers, and send reminders.',
    sections: [
      {
        id: 'types',
        heading: 'Default and follow-up',
        paragraphs: [
          'A default assignation gives each member their own session. A follow-up assignation is answered together in one shared session, and the people who answer it receive a daily reminder until it is completed; you then review each answer and can send it back for correction.',
        ],
      },
      {
        id: 'create',
        heading: 'Create an assignation',
        steps: [
          'Go to Assignations and click New.',
          'Choose the type, the organization and who responds: everybody, some people, an area or a role.',
          'Pick the questionnaire and give the assignation a name.',
          'Decide which registration fields the respondent fills in (email or phone must be required).',
          'Save and copy the link to share it.',
        ],
        paragraphs: [],
        screenshot: {
          name: 'assignations',
          alt: 'The assignation listing',
        },
      },
      {
        id: 'progress',
        heading: 'Follow the progress',
        paragraphs: [
          'The listing shows how many people or questions are complete. Open an assignation to see who completed it and who is pending, export the list to CSV, or send a reminder by email.',
        ],
        tip: 'A questionnaire belongs to one organization. If you assign one that is already assigned elsewhere, Mappi assigns a copy instead.',
      },
    ],
  },
  'projects': {
    title: 'Projects',
    summary:
      'Group the follow-up assignations of an organization under a deadline and see at a glance what needs your attention.',
    sections: [
      {
        id: 'what-for',
        heading: 'What a project is',
        paragraphs: [
          'A project groups follow-up assignations of one organization with a deadline. The project list tells you its state — not started, in progress, needs your review, in correction, completed or overdue — and the next step to take.',
        ],
        screenshot: {
          name: 'projects',
          alt: 'The project listing with its tabs',
        },
      },
      {
        id: 'wizard',
        heading: 'Create a project in three steps',
        paragraphs: [
          'New project walks you through the questions (draft them with AI or pick an existing questionnaire), the organization and its audience, and the project with its deadline. Nothing is saved until you click Create; then Mappi creates the questionnaire, the assignation and the project together.',
        ],
      },
      {
        id: 'review',
        heading: 'Review and correct',
        paragraphs: [
          'Open a project to see each assignation with its answers and review state. Approve or reject each answer with a comment, and send the rejected ones for correction: the respondents receive a new attempt by email.',
        ],
        tip: 'Use the tabs (To review, In correction, Overdue…) to see only the projects that need you.',
      },
    ],
  },
  'dashboard': {
    title: 'Read the dashboard',
    summary:
      'Completion rate, drop-off, funnel and one chart per question: how to read the analytics of a questionnaire.',
    sections: [
      {
        id: 'open',
        heading: 'Open the dashboard',
        paragraphs: [
          'Click Analytics in the row of a questionnaire. The first time, Mappi chooses the best chart for each question, which can take up to half a minute.',
        ],
        screenshot: {
          name: 'dashboard',
          alt: 'The dashboard of a questionnaire',
        },
      },
      {
        id: 'summary',
        heading: 'The summary',
        paragraphs: [
          'The top cards show the completion rate (completed sessions out of all started), the number of sessions, the average time to complete and the question where most people stop answering.',
        ],
      },
      {
        id: 'funnel',
        heading: 'The funnel',
        paragraphs: [
          'The funnel goes from Started through each question to Completed, so you can see exactly where people leave. Long stretches without drop-off are grouped to keep it short.',
        ],
      },
      {
        id: 'charts',
        heading: 'Charts per question',
        paragraphs: [
          'Each question has its own chart: distributions for options, histograms and gauges for scales, and an NPS breakdown for 0–10 scales (detractors 0–6, passives 7–8, promoters 9–10).',
        ],
      },
    ],
  },
  'answers-and-exports': {
    title: 'Answers and exports',
    summary:
      'Filter the answers, open each session in detail and export them to a spreadsheet.',
    sections: [
      {
        id: 'filter',
        heading: 'Filter the answers',
        paragraphs: [
          'Answers shows completed sessions by default. Change the status filter to see the ones still being filled, switch dates between your local time and UTC, and choose how many rows to load per page.',
        ],
        screenshot: {
          name: 'answers',
          alt: 'The answers with the status filter',
        },
      },
      {
        id: 'detail',
        heading: 'The detail of a session',
        paragraphs: [
          'Each session shows the respondent data, the total time, every question with its answer and the time spent, and the result the person received: the tier and score of a diagnostic, the recommended products or the generated profile.',
        ],
      },
      {
        id: 'export',
        heading: 'Export to a spreadsheet',
        paragraphs: [
          'Use Export to Google Sheets to send every answer to a new spreadsheet. In an assignation you can also export the list of respondents with their status to CSV.',
        ],
      },
    ],
  },
  'brand-customization': {
    title: 'Customize your brand',
    summary:
      'Make the respondent screens look like your brand: logo, font and color, fetched from your website.',
    sections: [
      {
        id: 'from-website',
        heading: 'Start from your website',
        paragraphs: [
          'In Customization, write your website URL and save: Mappi reads your site and picks your logo, colors and font for you. You can adjust everything afterwards.',
        ],
        screenshot: {
          name: 'customization',
          alt: 'Customization with the live preview',
        },
      },
      {
        id: 'adjust',
        heading: 'Adjust by hand',
        paragraphs: [
          'Choose one of six fonts, paste the URL of your logo and pick your brand color. From that color Mappi derives the buttons, the hover shade, readable text, links and the focus border of the fields. The preview shows what respondents will see.',
        ],
      },
      {
        id: 'save',
        heading: 'Save',
        paragraphs: [
          'Click Save to apply the styles to every questionnaire of the account. Reset only restores the defaults on screen until you save.',
        ],
      },
    ],
  },
  'store-quiz-funnel': {
    title: 'Recommend products with a quiz funnel',
    summary:
      'Connect your store or read your website, and generate a quiz that recommends the right product at the end.',
    sections: [
      {
        id: 'store',
        heading: 'Step 1: your store',
        paragraphs: [
          'Create a Quiz Funnel from New Questionnaire. If your store runs on Shopify, authorize the connection and load your products. Otherwise, write the URL of your store and choose how many products to read (5, 10, 20 or 30).',
        ],
      },
      {
        id: 'products',
        heading: 'Step 2: products',
        paragraphs: [
          'Review the products Mappi found and remove the ones you do not want to recommend. Loading them is optional: Generate reads your website if you skip this step.',
        ],
      },
      {
        id: 'generate',
        heading: 'Step 3: generate',
        paragraphs: [
          'Choose the kind of quiz — an experience or a profiling — and click Generate. In a few minutes you get a questionnaire whose result recommends products from your catalogue, with their link and price.',
        ],
        tip: 'With Shopify, enable the Mappi embed in your theme editor so the quiz appears in your store.',
      },
    ],
  },
  'users-and-roles': {
    title: 'Users and roles',
    summary:
      'Invite your team, choose who can make changes, and understand what the owner of the account can do.',
    sections: [
      {
        id: 'roles',
        heading: 'Roles',
        paragraphs: [
          'Admin users have full access, including creating other users. Read-only users can see everything but cannot make changes. The owner is the user who created the account and always has full access.',
        ],
        screenshot: {name: 'users', alt: 'The users of the account'},
      },
      {
        id: 'invite',
        heading: 'Add a user',
        steps: [
          'Go to Users and click New user.',
          'Write the full name, the email and a password of at least 8 characters.',
          'Choose the role: Admin or Read only.',
          'Click Create and share the credentials with your teammate.',
        ],
        paragraphs: [],
      },
      {
        id: 'profile',
        heading: 'Account settings',
        paragraphs: [
          'In Profile → Settings, choose the account language (it changes the emails and the respondent screens, not the console), the maximum files per question and your tracking pixels.',
        ],
      },
    ],
  },
};
