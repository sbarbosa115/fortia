import type {GuideId, GuideText} from '../model/types';

/** The 14 documentation guides in English (PRD §10.18). Same ids, order and topics as content/es.ts. */
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
          'Mappi helps you collect information with questionnaires. You design a questionnaire (often with the help of AI), send it to the people who should answer it, and follow their answers until you have everything you need: read them, review them, ask for corrections and see the results in a dashboard.',
          'Everything happens in the console. People who answer never need a Mappi account: they open a link and answer.',
        ],
      },
      {
        id: 'sidebar',
        heading: 'The sidebar',
        paragraphs: [
          'The sidebar groups the console in three blocks. Design holds AI Experience (the home page), Design Experience, Questionnaires and Customization. Send and track holds Organizations and Assignations. Settings holds Users, Profile and this Documentation.',
          'At the bottom you will find the language selector and your account, with the button to log out. The language selector only changes the language of the console; the language of the emails and of the respondent screens is your account language, in Profile.',
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
          'Create a questionnaire in AI Experience, or in Design Experience step by step.',
          'Share its public link, or send it to an organization with an assignation and a deadline.',
          'Follow the answers as they arrive: in Answers for the public link, in the assignation for an organization.',
          'Review the answers, ask for corrections when something is missing, and read the dashboard.',
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
      'Write the details, add the questions and decide what people see at the end — in three steps, with a live preview.',
    sections: [
      {
        id: 'start',
        heading: 'Start a questionnaire',
        paragraphs: [
          'Click Design Experience in the sidebar, or New Questionnaire in Questionnaires, and choose Regular: a classic questionnaire that ends with the message of your choice. On the left you build it in three steps; on the right a live preview shows, on mobile or desktop, what the respondent will see.',
        ],
        screenshot: {
          name: 'questionnaire-editor',
          alt: 'The questionnaire editor with its steps and the live preview',
        },
      },
      {
        id: 'details',
        heading: 'Step 1: details',
        paragraphs: [
          'Write a title (required), a description and, if you want, a custom link (slug): lowercase letters, numbers and hyphens. Leave the slug empty and Mappi generates it from the title.',
          'Add tags to find the questionnaire again later, for example AP-03: press Enter or a comma after each one (up to 20). You can also turn on a landing page with the title and description, and a disclaimer the respondent must accept before starting.',
        ],
      },
      {
        id: 'questions',
        heading: 'Step 2: questions',
        paragraphs: [
          'Click Add Question and choose its type: selection, dropdown, ranking, text, audio, range, file, table or a message. Each question has a title, an optional description and category, and can be required or not. Drag questions to reorder them or to move them to another category, and duplicate the ones you want to reuse.',
        ],
        tip: 'The guide "Question types" explains each type, including tables and file templates.',
      },
      {
        id: 'ending',
        heading: 'Step 3: when it ends',
        paragraphs: [
          'Decide what the respondent sees at the end by adding elements from the list: a thank-you message, a call to action (a button with a link, which always opens in a new tab) and a form to capture their name, email and phone.',
          'Nothing is saved until you click Create and confirm. Then you can copy the link, view the questionnaire, keep editing or create another.',
        ],
      },
    ],
  },
  'question-types': {
    title: 'Question types',
    summary:
      'Which type to choose for each question, and how to set up tables, file uploads and follow-ups for open answers.',
    sections: [
      {
        id: 'choices',
        heading: 'Choices',
        paragraphs: [
          'Single selection, multiple selection and dropdown show a list of choices you write. Ranking asks the respondent to drag the choices into order of preference. Range shows a slider between the minimum and maximum you set; a 0–10 range gets an NPS chart in the dashboard.',
        ],
      },
      {
        id: 'open',
        heading: 'Text and audio',
        paragraphs: [
          'Text and audio questions take an open answer. For a text question you can restrict the data type (free, RFC, NIT or phone) and the allowed characters (letters, numbers, symbols).',
          'Both can ask follow-up questions: set Max follow-ups (up to 5) and write up to 10 acceptance criteria. When the AI finds that an answer does not meet them, it asks the respondent to expand on it.',
        ],
      },
      {
        id: 'tables',
        heading: 'Tables',
        paragraphs: [
          'A table question is built on the table itself, exactly as the respondent will see it. Type on the headers to name the columns (up to 20); use the menu of a column to rename it, move it or delete it.',
          'Choose who writes the rows. With fixed rows you name each row in its first cell and the respondent fills in every one. With "The respondent may add rows" they add their own, up to the limit you set (up to 50).',
        ],
      },
      {
        id: 'files',
        heading: 'Files and templates',
        paragraphs: [
          'A file question lets the respondent upload files. You can attach a template (up to 20 MB) that the respondent downloads, fills in and uploads back. How many files can be attached to one question is set in Profile → Settings (1 to 20).',
        ],
        tip: 'A message question asks for no answer: use it to give instructions or context between questions.',
      },
    ],
  },
  'create-with-ai': {
    title: 'Create a questionnaire with AI',
    summary:
      'Describe what you need in AI Experience, or attach a document with your questions, and let the assistant build the questionnaire with you.',
    sections: [
      {
        id: 'start-a-chat',
        heading: 'Start a chat',
        paragraphs: [
          'AI Experience is the home page of the console. Write what you want to create — for example "a supplier evaluation questionnaire, 8 questions, tagged AP-03" — and press Enter. Shift+Enter adds a line break.',
          'The assistant answers with a draft that appears in the live preview on the right, where you can go through the welcome, the questions and the result as a respondent would.',
        ],
        screenshot: {
          name: 'ai-experience',
          alt: 'AI Experience with the chat and the live preview',
        },
      },
      {
        id: 'documents',
        heading: 'Build it from a document',
        paragraphs: [
          'Do you already have the questions? Paste them in the chat, or attach a Word, PDF, Markdown, text or CSV document with the clip button. The assistant reads it and builds the questionnaire with those questions.',
        ],
      },
      {
        id: 'refine',
        heading: 'Refine the draft',
        paragraphs: [
          'Ask for changes in plain words: add a question, change the title or the tone, translate it, add tags. Quick replies appear as buttons when the assistant offers options.',
        ],
        tip: 'Long conversations are fine: the assistant always works with the most recent messages. Click New chat to start over.',
      },
      {
        id: 'create',
        heading: 'Create it',
        paragraphs: [
          'When you are happy with the draft, ask the assistant to create it. A "Questionnaire created" card appears with buttons to edit or view it. From there it is a normal questionnaire: you can edit it, share it and assign it.',
          'The assistant can also answer questions about your account, such as the details of a questionnaire, an organization or an assignation.',
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
          'Questionnaires lists every questionnaire of your account. Search by title or tag (Ctrl+K focuses the search box), filter by type and state, and sort by creation or update date. Each row shows the number of questions, the tags, the type and an Active toggle.',
          'The icons of each row let you view the questionnaire, copy its link, edit it and open its analytics; the Answers button opens its answers.',
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
          'Click the edit icon to open the same three steps you used to create it. There is no autosave: click Save changes when you are done.',
        ],
      },
      {
        id: 'locked',
        heading: 'Locked questionnaires',
        paragraphs: [
          'Once a questionnaire has answers it is locked, so the answers keep matching the questions they were given for. It still opens in the editor, read-only, under the "Locked to preserve answers" banner. To change it, click Create a copy: you get a new questionnaire with no answers, ready to edit.',
        ],
        tip: 'Copies keep the questions, the ending, the tags and the settings, with a new link.',
      },
    ],
  },
  'share-and-collect': {
    title: 'Share a questionnaire and collect answers',
    summary:
      'Share the public link of a questionnaire and follow every answer as it arrives.',
    sections: [
      {
        id: 'public-link',
        heading: 'The public link',
        paragraphs: [
          'Every questionnaire has a public link built from its slug. Copy it from the listing or from the success screen after creating it, and share it by email, on your website or on social media. Anyone with the link can answer while the questionnaire is active.',
        ],
        tip: 'To send a questionnaire to the members of an organization, with a deadline and reminders, use an assignation instead.',
      },
      {
        id: 'test',
        heading: 'Test it first',
        paragraphs: [
          'Open the link yourself before sharing it: answer it as a respondent and check the end screen. Your test answers appear in Answers like any other.',
        ],
      },
      {
        id: 'answers',
        heading: 'Follow the answers',
        paragraphs: [
          'Click Answers in the row of the questionnaire. Each session shows when it started, the name, email and phone if the respondent left them, the progress and the state: Filling, Filled out, Processing or Completed. Click View to read every answer with the time spent on it.',
        ],
        screenshot: {name: 'answers', alt: 'The answers of a questionnaire'},
      },
      {
        id: 'files',
        heading: 'Files, audio and tables',
        paragraphs: [
          'Images, PDFs, audio and video that respondents upload can be previewed in the answer detail, and any file can be downloaded. Table answers are shown as a table.',
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
          'An organization is a company, a client or a team whose members will answer your questionnaires. Every assignation is made for one organization, and all its members can answer it.',
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
          'Click New Organization and write its name. The email domain is optional: when you set it, Mappi warns you about members whose email uses another domain, but saves them anyway. You can also create an organization without leaving the assignation wizard.',
        ],
      },
      {
        id: 'members',
        heading: 'Add members',
        paragraphs: [
          'Each member needs a name and at least an email or a phone; role and area are optional. Members sign in to their assignations with the email or phone you saved here, so check them carefully.',
        ],
      },
      {
        id: 'csv',
        heading: 'Import from CSV',
        paragraphs: [
          'Download the template, fill in one row per member and import it. The file can use commas or semicolons and the columns can be in English or Spanish (name/nombre, email/correo, phone/telefono, role/rol, area). Rows that cannot be imported are listed with the reason.',
        ],
        tip: 'Deleting an organization deletes its members. It is refused while assignations still use it.',
      },
    ],
  },
  'assignations': {
    title: 'Send questionnaires with assignations',
    summary:
      'Send one or more questionnaires to an organization with a deadline, and let its members answer them together.',
    sections: [
      {
        id: 'what-for',
        heading: 'What an assignation is',
        paragraphs: [
          'An assignation sends one or more questionnaires to one organization, with a deadline. Each questionnaire is answered together: the members of the organization share one session, so any of them can carry it on where another left it.',
        ],
      },
      {
        id: 'create',
        heading: 'Create an assignation',
        paragraphs: [],
        steps: [
          'Go to Assignations and click New assignation.',
          'Questionnaires: pick every questionnaire the organization will answer. Search them by name or tag, or filter them by one or more tags.',
          'Organization: choose who answers, or create a new organization right there.',
          'Name and deadline: give it a name only your team sees, choose the deadline and decide whether it requires review.',
          'Click Create. Nothing is saved before that.',
        ],
        screenshot: {
          name: 'assignation-new',
          alt: 'The new assignation wizard',
        },
      },
      {
        id: 'answering',
        heading: 'How members answer',
        paragraphs: [
          'Copy the link of a questionnaire from the assignation and send it to the organization. Members sign in with the email or phone they have in the organization and continue from the question where the session is.',
          'Until a questionnaire is finished, the members receive a daily reminder: one email per person, listing everything they still have pending. You can also send a reminder at any time with Send reminder.',
        ],
      },
      {
        id: 'review',
        heading: 'With or without review',
        paragraphs: [
          'With "Requires review" on, a completed questionnaire goes to To review: you approve its answers or send it back for correction. With it off, a completed questionnaire is simply marked Completed and its answers are final.',
        ],
        tip: 'You can assign the same questionnaire to as many organizations as you need, without copies. Each assignation keeps its own answers.',
      },
    ],
  },
  'review-and-corrections': {
    title: 'Follow up, review and correct',
    summary:
      'See which assignations need you, review each answer, and send what is missing back for correction.',
    sections: [
      {
        id: 'the-listing',
        heading: 'The assignation listing',
        paragraphs: [
          'Assignations shows each assignation with its organization, its status, how many questionnaires are done, the deadline and the next step to take. An assignation shows the status of its most urgent questionnaire. Expand a row to see its questionnaires and the question each one is on.',
          'Use the tabs — All, To review, In progress, In correction, Overdue and Completed — and the search box to see only what needs you.',
        ],
        screenshot: {
          name: 'assignations',
          alt: 'The assignation listing with its tabs',
        },
      },
      {
        id: 'statuses',
        heading: 'How a questionnaire moves forward',
        paragraphs: [
          'No answers: you sent the link and nobody has started. Answering: part of it is answered. To review: everything is answered and it is your turn. In correction: you asked to correct some answers. Approved or Completed: it is finished. Overdue: the deadline passed before it was finished.',
        ],
      },
      {
        id: 'review',
        heading: 'Review the answers',
        paragraphs: [
          'Open a questionnaire of the assignation to see the table of answers of the current attempt. Click an answer to approve or reject it, with an optional comment the respondents read when they correct it.',
        ],
        screenshot: {
          name: 'assignation-detail',
          alt: 'The answers of a questionnaire in an assignation, with their review',
        },
      },
      {
        id: 'correct',
        heading: 'Send for correction',
        paragraphs: [
          'When every answer is reviewed and at least one is rejected, click Send for correction. A new attempt opens with the approved answers kept, and the people who answer receive an email to correct the rejected ones. Previous attempts can still be read from the Attempt selector.',
        ],
        tip: 'From the listing you can also edit an assignation (name, description, deadline and review) or delete it; its questionnaires and their answers are kept.',
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
          'Click the analytics icon in the row of a questionnaire, or Dashboard in its answers. The first time, Mappi chooses the best chart for each question, which can take up to half a minute.',
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
          'Each question has its own chart: distributions for choices, histograms and gauges for scales, and an NPS breakdown for 0–10 scales (detractors 0–6, passives 7–8, promoters 9–10).',
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
          'Answers shows completed sessions by default. Change the state filter to see the ones still being filled, switch dates between your local time and UTC, and choose how many rows to load per page.',
        ],
        screenshot: {
          name: 'answers',
          alt: 'The answers with the state filter',
        },
      },
      {
        id: 'detail',
        heading: 'The detail of a session',
        paragraphs: [
          'Each session shows the respondent data, the total time, and every question with its answer and the time spent on it.',
        ],
      },
      {
        id: 'export',
        heading: 'Export to a spreadsheet',
        paragraphs: [
          'Use Export to Google Sheets to send every answer to a new spreadsheet. While Google Sheets is not configured on the server, the button stays disabled and says so.',
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
          'Choose a font, paste the URL of your logo and pick your brand color. From that color Mappi derives the buttons, the hover shade, readable text, links and the focus border of the fields. The preview shows what respondents will see.',
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
  'users-and-roles': {
    title: 'Users and roles',
    summary:
      'Invite your team, choose who can make changes, and set the language and limits of the account.',
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
        paragraphs: [],
        steps: [
          'Go to Users and click New user.',
          'Write the full name, the email and a password of at least 8 characters.',
          'Choose the role: Admin or Read only.',
          'Click Create user and share the credentials with your teammate.',
        ],
      },
      {
        id: 'profile',
        heading: 'Account settings',
        paragraphs: [
          'In Profile → Settings, choose the account language (it changes the emails and the respondent screens, not the console), the maximum files per question and your tracking pixels (Meta, LinkedIn and Google Ads).',
        ],
      },
    ],
  },
  'system-settings': {
    title: 'Email server, OpenAI and analytics',
    summary:
      'Send the account emails through your own server, bill the AI to your OpenAI key and receive the account events in your own service.',
    sections: [
      {
        id: 'where',
        heading: 'The System tab',
        paragraphs: [
          'Open Profile → System. Each block says whether the account uses its own service ("Your server", "Your key") or Mappi\'s. Passwords and keys are stored encrypted and only their last 4 characters are ever shown.',
        ],
        screenshot: {
          name: 'profile-system',
          alt: 'The System tab of Profile',
        },
      },
      {
        id: 'smtp',
        heading: 'Email server (SMTP)',
        paragraphs: [
          "Reminders, follow-up status and correction emails can be sent through your own server. Write the server, port, encryption, username, password and sender, then click Validate: Mappi sends a test email and tells you what failed if it could not. Use Mappi's server to go back.",
        ],
      },
      {
        id: 'openai',
        heading: 'OpenAI API key',
        paragraphs: [
          "The AI features (the chat, questionnaire generation, dashboards and the evaluation of open answers) run on OpenAI. With your own key they are billed to your OpenAI account; without one, Mappi's key is used. Remove my key goes back to Mappi's.",
        ],
      },
      {
        id: 'analytics',
        heading: 'Analytics service',
        paragraphs: [
          "Every event of the account (questionnaires created, answers, sign-ins…) is sent to an analytics service. Set your own endpoint and API key to receive them at {base URL}/events; without one, Mappi's service is used.",
        ],
      },
    ],
  },
};
