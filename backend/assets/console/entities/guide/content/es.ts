import type {GuideId, GuideText} from '../model/types';

/** Las 14 guías de la documentación en español (PRD §10.18). Mismos ids, orden y temas que content/en.ts. */
export const GUIDES_ES: Record<GuideId, GuideText> = {
  'welcome': {
    title: 'Bienvenido a Mappi',
    summary:
      'Un recorrido por la consola: para qué sirve cada sección del menú y cómo un cuestionario pasa de la idea a los resultados.',
    sections: [
      {
        id: 'que-es-mappi',
        heading: 'Qué hace Mappi',
        paragraphs: [
          'Mappi te ayuda a recolectar información con cuestionarios. Diseñas un cuestionario (muchas veces con ayuda de la IA), lo envías a las personas que deben responderlo y sigues sus respuestas hasta tener todo lo que necesitas: las lees, las revisas, pides correcciones y ves los resultados en un tablero.',
          'Todo sucede en la consola. Quien responde nunca necesita una cuenta de Mappi: abre un enlace y responde.',
        ],
      },
      {
        id: 'menu',
        heading: 'El menú lateral',
        paragraphs: [
          'El menú agrupa la consola en tres bloques. Diseñar reúne Experiencia IA (la página de inicio), Diseñar experiencia, Cuestionarios y Personalización. Enviar y hacer seguimiento reúne Organizaciones y Asignaciones. Configuración reúne Usuarios, Perfil y esta Documentación.',
          'Abajo encuentras el selector de idioma y tu cuenta, con el botón para cerrar sesión. El selector de idioma solo cambia el idioma de la consola; el idioma de los correos y de las pantallas de quien responde es el idioma de tu cuenta, en Perfil.',
        ],
        screenshot: {
          name: 'ai-experience',
          alt: 'La consola con el menú lateral y el chat de Experiencia IA',
        },
      },
      {
        id: 'el-recorrido',
        heading: 'De la idea a los resultados',
        paragraphs: ['Un recorrido típico tiene cuatro pasos:'],
        steps: [
          'Crea un cuestionario en Experiencia IA, o paso a paso en Diseñar experiencia.',
          'Comparte su enlace público, o envíalo a una organización con una asignación y una fecha límite.',
          'Sigue las respuestas a medida que llegan: en Respuestas para el enlace público, en la asignación para una organización.',
          'Revisa las respuestas, pide correcciones cuando falte algo y lee el tablero.',
        ],
      },
      {
        id: 'roles',
        heading: 'Usuarios de solo lectura',
        paragraphs: [
          'Si tu rol es de solo lectura puedes verlo todo, pero los botones que crean o cambian algo están deshabilitados. Pasa el cursor sobre un botón deshabilitado para leer el motivo.',
        ],
      },
    ],
  },
  'first-questionnaire': {
    title: 'Crea tu primer cuestionario',
    summary:
      'Escribe los detalles, agrega las preguntas y decide qué ven las personas al final, en tres pasos y con vista previa en vivo.',
    sections: [
      {
        id: 'empieza',
        heading: 'Empieza un cuestionario',
        paragraphs: [
          'Haz clic en Diseñar experiencia en el menú, o en Nuevo cuestionario en Cuestionarios, y elige Regular: un cuestionario clásico que termina con el mensaje que tú elijas. A la izquierda lo construyes en tres pasos; a la derecha una vista previa en vivo muestra, en móvil o escritorio, lo que verá quien responde.',
        ],
        screenshot: {
          name: 'questionnaire-editor',
          alt: 'El editor de cuestionarios con sus pasos y la vista previa en vivo',
        },
      },
      {
        id: 'detalles',
        heading: 'Paso 1: detalles',
        paragraphs: [
          'Escribe un título (obligatorio), una descripción y, si quieres, un enlace personalizado (slug): letras minúsculas, números y guiones. Si dejas el slug vacío, Mappi lo genera a partir del título.',
          'Agrega etiquetas para encontrar el cuestionario más adelante, por ejemplo AP-03: presiona Enter o una coma después de cada una (hasta 20). También puedes activar una landing page con el título y la descripción, y un aviso que quien responde debe aceptar antes de empezar.',
        ],
      },
      {
        id: 'preguntas',
        heading: 'Paso 2: preguntas',
        paragraphs: [
          'Haz clic en Agregar pregunta y elige su tipo: selección, lista desplegable, ranking, texto, audio, rango, archivo, tabla o un mensaje. Cada pregunta tiene un título, una descripción y una categoría opcionales, y puede ser obligatoria o no. Arrastra las preguntas para reordenarlas o pasarlas a otra categoría, y duplica las que quieras reutilizar.',
        ],
        tip: 'La guía «Tipos de pregunta» explica cada tipo, incluidas las tablas y las plantillas de archivo.',
      },
      {
        id: 'al-terminar',
        heading: 'Paso 3: al terminar',
        paragraphs: [
          'Decide qué ve quien responde al final agregando elementos de la lista: un mensaje de agradecimiento, un llamado a la acción (un botón con enlace, que siempre se abre en una pestaña nueva) y un formulario para capturar su nombre, correo y teléfono.',
          'Nada se guarda hasta que haces clic en Crear y confirmas. Después puedes copiar el enlace, ver el cuestionario, seguir editando o crear otro.',
        ],
      },
    ],
  },
  'question-types': {
    title: 'Tipos de pregunta',
    summary:
      'Qué tipo elegir para cada pregunta, y cómo configurar tablas, archivos y preguntas de seguimiento para las respuestas abiertas.',
    sections: [
      {
        id: 'opciones',
        heading: 'Opciones',
        paragraphs: [
          'Selección única, selección múltiple y lista desplegable muestran una lista de opciones que tú escribes. Ranking pide ordenar las opciones por preferencia arrastrándolas. Rango muestra un deslizador entre el mínimo y el máximo que definas; un rango de 0 a 10 tiene una gráfica NPS en el tablero.',
        ],
      },
      {
        id: 'abiertas',
        heading: 'Texto y audio',
        paragraphs: [
          'Las preguntas de texto y de audio reciben una respuesta abierta. En una de texto puedes limitar el tipo de dato (libre, RFC, NIT o teléfono) y los caracteres permitidos (letras, números, símbolos).',
          'Ambas pueden hacer preguntas de seguimiento: define el máximo de seguimientos (hasta 5) y escribe hasta 10 criterios de aceptación. Cuando la IA ve que una respuesta no los cumple, le pide a la persona que la amplíe.',
        ],
      },
      {
        id: 'tablas',
        heading: 'Tablas',
        paragraphs: [
          'Una pregunta de tabla se construye sobre la misma tabla, tal como la verá quien responde. Escribe en los encabezados para nombrar las columnas (hasta 20); usa el menú de cada columna para renombrarla, moverla o eliminarla.',
          'Elige quién escribe las filas. Con filas fijas nombras cada fila en su primera celda y la persona llena todas. Con «La persona podrá agregar filas», ella agrega las suyas, hasta el límite que definas (hasta 50).',
        ],
      },
      {
        id: 'archivos',
        heading: 'Archivos y plantillas',
        paragraphs: [
          'Una pregunta de archivo permite subir archivos. Puedes adjuntar una plantilla (hasta 20 MB) que la persona descarga, llena y vuelve a subir. Cuántos archivos se pueden adjuntar en una pregunta se define en Perfil → Configuración (de 1 a 20).',
        ],
        tip: 'Una pregunta de mensaje no pide respuesta: úsala para dar instrucciones o contexto entre preguntas.',
      },
    ],
  },
  'create-with-ai': {
    title: 'Crea un cuestionario con IA',
    summary:
      'Describe lo que necesitas en Experiencia IA, o adjunta un documento con tus preguntas, y deja que el asistente construya el cuestionario contigo.',
    sections: [
      {
        id: 'inicia-un-chat',
        heading: 'Inicia un chat',
        paragraphs: [
          'Experiencia IA es la página de inicio de la consola. Escribe lo que quieres crear, por ejemplo «un cuestionario de evaluación de proveedores, 8 preguntas, con la etiqueta AP-03», y presiona Enter. Shift+Enter agrega un salto de línea.',
          'El asistente responde con un borrador que aparece en la vista previa en vivo a la derecha, donde puedes recorrer la bienvenida, las preguntas y el resultado como lo haría quien responde.',
        ],
        screenshot: {
          name: 'ai-experience',
          alt: 'Experiencia IA, lista para tu primer mensaje',
        },
      },
      {
        id: 'documentos',
        heading: 'Constrúyelo desde un documento',
        paragraphs: [
          '¿Ya tienes las preguntas? Pégalas en el chat, o adjunta un documento Word, PDF, Markdown, de texto o CSV con el botón del clip. El asistente lo lee y construye el cuestionario con esas preguntas.',
        ],
      },
      {
        id: 'ajusta',
        heading: 'Ajusta el borrador',
        paragraphs: [
          'Pide cambios con tus palabras: agrega una pregunta, cambia el título o el tono, tradúcelo, ponle etiquetas. Cuando el asistente ofrece opciones, aparecen como botones de respuesta rápida.',
        ],
        tip: 'Las conversaciones largas no son problema: el asistente siempre trabaja con los mensajes más recientes. Haz clic en Nuevo chat para empezar de cero.',
      },
      {
        id: 'crealo',
        heading: 'Créalo',
        paragraphs: [
          'Cuando el borrador te guste, pídele al asistente que lo cree. Aparece una tarjeta «Cuestionario creado» con botones para editarlo o verlo. Desde ahí es un cuestionario normal: puedes editarlo, compartirlo y asignarlo.',
          'El asistente también responde preguntas sobre tu cuenta, como los detalles de un cuestionario, una organización o una asignación.',
        ],
      },
    ],
  },
  'edit-questionnaires': {
    title: 'Edita y administra cuestionarios',
    summary:
      'Busca, filtra, edita, activa y copia cuestionarios desde el listado, y qué significa «Bloqueado».',
    sections: [
      {
        id: 'el-listado',
        heading: 'El listado',
        paragraphs: [
          'Cuestionarios lista todos los cuestionarios de tu cuenta. Busca por título o etiqueta (Ctrl+K lleva al buscador), filtra por tipo y estado, y ordena por fecha de creación o de actualización. Cada fila muestra el número de preguntas, las etiquetas, el tipo y un interruptor Activo.',
          'Los íconos de cada fila te permiten ver el cuestionario, copiar su enlace, editarlo y abrir su analítica; el botón Respuestas abre sus respuestas.',
        ],
        screenshot: {
          name: 'questionnaires',
          alt: 'El listado de cuestionarios con sus filtros',
        },
      },
      {
        id: 'activar',
        heading: 'Activo e inactivo',
        paragraphs: [
          'Solo los cuestionarios activos reciben respuestas. Apaga el interruptor para dejar de recibir respuestas sin borrar nada; vuelve a encenderlo cuando quieras.',
        ],
      },
      {
        id: 'editar',
        heading: 'Editar',
        paragraphs: [
          'Haz clic en el ícono de editar para abrir los mismos tres pasos que usaste al crearlo. No hay guardado automático: haz clic en Guardar cambios al terminar.',
        ],
      },
      {
        id: 'bloqueado',
        heading: 'Cuestionarios bloqueados',
        paragraphs: [
          'Cuando un cuestionario tiene respuestas queda bloqueado, para que las respuestas sigan correspondiendo a las preguntas que se respondieron. Se sigue abriendo en el editor, en solo lectura, bajo el aviso «Bloqueado para conservar las respuestas». Para cambiarlo, haz clic en Crear una copia: obtienes un cuestionario nuevo sin respuestas, listo para editar.',
        ],
        tip: 'Las copias conservan las preguntas, el final, las etiquetas y la configuración, con un enlace nuevo.',
      },
    ],
  },
  'share-and-collect': {
    title: 'Comparte un cuestionario y recibe respuestas',
    summary:
      'Comparte el enlace público de un cuestionario y sigue cada respuesta a medida que llega.',
    sections: [
      {
        id: 'enlace-publico',
        heading: 'El enlace público',
        paragraphs: [
          'Cada cuestionario tiene un enlace público construido con su slug. Cópialo desde el listado o desde la pantalla de éxito al crearlo, y compártelo por correo, en tu sitio web o en redes sociales. Cualquiera con el enlace puede responder mientras el cuestionario esté activo.',
        ],
        tip: 'Para enviar un cuestionario a los miembros de una organización, con fecha límite y recordatorios, usa una asignación.',
      },
      {
        id: 'pruebalo',
        heading: 'Pruébalo primero',
        paragraphs: [
          'Abre el enlace tú mismo antes de compartirlo: respóndelo como lo haría otra persona y revisa la pantalla final. Tus respuestas de prueba aparecen en Respuestas como cualquier otra.',
        ],
      },
      {
        id: 'respuestas',
        heading: 'Sigue las respuestas',
        paragraphs: [
          'Haz clic en Respuestas en la fila del cuestionario. Cada sesión muestra cuándo empezó, el nombre, el correo y el teléfono si la persona los dejó, el avance y el estado: Respondiendo, Enviada, Procesando o Completada. Haz clic en Ver para leer cada respuesta con el tiempo dedicado.',
        ],
        screenshot: {
          name: 'answers',
          alt: 'Las respuestas de un cuestionario',
        },
      },
      {
        id: 'archivos',
        heading: 'Archivos, audio y tablas',
        paragraphs: [
          'Las imágenes, PDF, audios y videos que suben las personas se pueden previsualizar en el detalle de la respuesta, y cualquier archivo se puede descargar. Las respuestas de tabla se muestran como una tabla.',
        ],
      },
    ],
  },
  'organizations-and-members': {
    title: 'Organizaciones y miembros',
    summary:
      'Guarda las empresas o equipos con los que trabajas, con sus miembros, e importa miembros desde un archivo CSV.',
    sections: [
      {
        id: 'para-que',
        heading: 'Para qué sirven las organizaciones',
        paragraphs: [
          'Una organización es una empresa, un cliente o un equipo cuyos miembros responderán tus cuestionarios. Cada asignación se hace para una organización, y todos sus miembros pueden responderla.',
        ],
        screenshot: {
          name: 'organizations',
          alt: 'Las tarjetas de las organizaciones',
        },
      },
      {
        id: 'crear',
        heading: 'Crea una organización',
        paragraphs: [
          'Haz clic en Nueva organización y escribe su nombre. El dominio de correo es opcional: si lo defines, Mappi te avisa de los miembros cuyo correo usa otro dominio, pero los guarda de todas formas. También puedes crear una organización sin salir del asistente de asignaciones.',
        ],
      },
      {
        id: 'miembros',
        heading: 'Agrega miembros',
        paragraphs: [
          'Cada miembro necesita un nombre y al menos un correo o un teléfono; el rol y el área son opcionales. Los miembros entran a sus asignaciones con el correo o el teléfono que guardaste aquí, así que revísalos bien.',
        ],
      },
      {
        id: 'csv',
        heading: 'Importa desde CSV',
        paragraphs: [
          'Descarga la plantilla, llena una fila por miembro e impórtala. El archivo puede usar comas o punto y coma, y las columnas pueden estar en español o en inglés (nombre/name, correo/email, telefono/phone, rol/role, area). Las filas que no se pueden importar se listan con el motivo.',
        ],
        tip: 'Eliminar una organización elimina sus miembros. No se permite mientras alguna asignación la use.',
      },
    ],
  },
  'assignations': {
    title: 'Envía cuestionarios con asignaciones',
    summary:
      'Envía uno o varios cuestionarios a una organización con una fecha límite, y deja que sus miembros los respondan juntos.',
    sections: [
      {
        id: 'que-es',
        heading: 'Qué es una asignación',
        paragraphs: [
          'Una asignación envía uno o varios cuestionarios a una organización, con una fecha límite. Cada cuestionario se responde en conjunto: los miembros de la organización comparten una sola sesión, así que cualquiera puede continuar donde otro lo dejó.',
        ],
      },
      {
        id: 'crear',
        heading: 'Crea una asignación',
        paragraphs: [],
        steps: [
          'Ve a Asignaciones y haz clic en Nueva asignación.',
          'Cuestionarios: elige todos los cuestionarios que responderá la organización. Búscalos por nombre o etiqueta, o fíltralos por una o varias etiquetas.',
          'Organización: elige quién responde, o crea una organización nueva ahí mismo.',
          'Nombre y fecha límite: ponle un nombre que solo ve tu equipo, elige la fecha límite y decide si requiere revisión.',
          'Haz clic en Crear. Nada se guarda antes.',
        ],
        screenshot: {
          name: 'assignation-new',
          alt: 'El asistente de nueva asignación',
        },
      },
      {
        id: 'como-responden',
        heading: 'Cómo responden los miembros',
        paragraphs: [
          'Copia el enlace de un cuestionario desde la asignación y envíalo a la organización. Los miembros entran con el correo o el teléfono que tienen en la organización y continúan desde la pregunta en la que va la sesión.',
          'Mientras un cuestionario no esté terminado, los miembros reciben un recordatorio diario: un correo por persona con todo lo que tiene pendiente. También puedes enviar un recordatorio en cualquier momento con Enviar recordatorio.',
        ],
      },
      {
        id: 'revision',
        heading: 'Con o sin revisión',
        paragraphs: [
          'Con «Requiere revisión» activado, un cuestionario completado pasa a Por revisar: apruebas sus respuestas o lo devuelves a corrección. Desactivado, un cuestionario completado queda como Completado y sus respuestas son definitivas.',
        ],
        tip: 'Puedes asignar el mismo cuestionario a todas las organizaciones que necesites, sin copias. Cada asignación guarda sus propias respuestas.',
      },
    ],
  },
  'review-and-corrections': {
    title: 'Sigue, revisa y corrige',
    summary:
      'Mira qué asignaciones te necesitan, revisa cada respuesta y devuelve a corrección lo que falte.',
    sections: [
      {
        id: 'el-listado',
        heading: 'El listado de asignaciones',
        paragraphs: [
          'Asignaciones muestra cada asignación con su organización, su estado, cuántos cuestionarios están listos, la fecha límite y el siguiente paso. El estado de una asignación es el de su cuestionario más urgente. Despliega una fila para ver sus cuestionarios y en qué pregunta va cada uno.',
          'Usa las pestañas (Todas, Por revisar, En progreso, En corrección, Vencidas y Completadas) y el buscador para ver solo lo que te necesita.',
        ],
        screenshot: {
          name: 'assignations',
          alt: 'El listado de asignaciones con sus pestañas',
        },
      },
      {
        id: 'estados',
        heading: 'Cómo avanza un cuestionario',
        paragraphs: [
          'Sin respuestas: enviaste el enlace y nadie ha empezado. Respondiendo: hay una parte respondida. Por revisar: todo está respondido y te toca a ti. En corrección: pediste corregir algunas respuestas. Aprobada o Completado: está terminado. Vencida: pasó la fecha límite sin terminar.',
        ],
      },
      {
        id: 'revisa',
        heading: 'Revisa las respuestas',
        paragraphs: [
          'Abre un cuestionario de la asignación para ver la tabla de respuestas del intento actual. Haz clic en una respuesta para aprobarla o rechazarla, con un comentario opcional que leen quienes responden al corregirla.',
        ],
        screenshot: {
          name: 'assignation-detail',
          alt: 'Las respuestas de un cuestionario en una asignación, con su revisión',
        },
      },
      {
        id: 'correccion',
        heading: 'Envía a corrección',
        paragraphs: [
          'Cuando todas las respuestas están revisadas y al menos una está rechazada, haz clic en Enviar a corrección. Se abre un intento nuevo conservando las respuestas aprobadas, y quienes responden reciben un correo para corregir las rechazadas. Los intentos anteriores se pueden leer desde el selector de Intento.',
        ],
        tip: 'Desde el listado también puedes editar una asignación (nombre, descripción, fecha límite y revisión) o eliminarla; sus cuestionarios y sus respuestas se conservan.',
      },
    ],
  },
  'dashboard': {
    title: 'Lee el tablero',
    summary:
      'Tasa de finalización, abandono, embudo y una gráfica por pregunta: cómo leer la analítica de un cuestionario.',
    sections: [
      {
        id: 'abrir',
        heading: 'Abre el tablero',
        paragraphs: [
          'Haz clic en el ícono de analítica en la fila de un cuestionario, o en Tablero desde sus respuestas. La primera vez, Mappi elige la mejor gráfica para cada pregunta, lo que puede tardar hasta medio minuto.',
        ],
        screenshot: {
          name: 'dashboard',
          alt: 'El tablero de un cuestionario',
        },
      },
      {
        id: 'resumen',
        heading: 'El resumen',
        paragraphs: [
          'Las tarjetas superiores muestran la tasa de finalización (sesiones completadas sobre todas las iniciadas), el número de sesiones, el tiempo promedio para terminar y la pregunta donde más personas dejan de responder.',
        ],
      },
      {
        id: 'embudo',
        heading: 'El embudo',
        paragraphs: [
          'El embudo va de Iniciaron, pasando por cada pregunta, hasta Completaron, para que veas exactamente dónde se van las personas. Los tramos largos sin abandono se agrupan para que sea corto.',
        ],
      },
      {
        id: 'graficas',
        heading: 'Gráficas por pregunta',
        paragraphs: [
          'Cada pregunta tiene su propia gráfica: distribuciones para las opciones, histogramas e indicadores para las escalas, y un desglose NPS para escalas de 0 a 10 (detractores 0–6, pasivos 7–8, promotores 9–10).',
        ],
      },
    ],
  },
  'answers-and-exports': {
    title: 'Respuestas y exportaciones',
    summary:
      'Filtra las respuestas, abre cada sesión en detalle y expórtalas a una hoja de cálculo.',
    sections: [
      {
        id: 'filtrar',
        heading: 'Filtra las respuestas',
        paragraphs: [
          'Respuestas muestra por defecto las sesiones completadas. Cambia el filtro de estado para ver las que aún se están llenando, alterna las fechas entre tu hora local y UTC, y elige cuántas filas cargar por página.',
        ],
        screenshot: {
          name: 'answers',
          alt: 'Las respuestas con el filtro de estado',
        },
      },
      {
        id: 'detalle',
        heading: 'El detalle de una sesión',
        paragraphs: [
          'Cada sesión muestra los datos de quien respondió, el tiempo total, y cada pregunta con su respuesta y el tiempo dedicado.',
        ],
      },
      {
        id: 'exportar',
        heading: 'Exporta a una hoja de cálculo',
        paragraphs: [
          'Usa Exportar a Google Sheets para enviar todas las respuestas a una hoja de cálculo nueva. Mientras Google Sheets no esté configurado en el servidor, el botón queda deshabilitado y lo indica.',
        ],
      },
    ],
  },
  'brand-customization': {
    title: 'Personaliza tu marca',
    summary:
      'Haz que las pantallas de quien responde se vean como tu marca: logo, fuente y color, tomados de tu sitio web.',
    sections: [
      {
        id: 'desde-tu-sitio',
        heading: 'Empieza desde tu sitio web',
        paragraphs: [
          'En Personalización, escribe la URL de tu sitio web y guarda: Mappi lee tu sitio y elige tu logo, colores y fuente por ti. Después puedes ajustarlo todo.',
        ],
        screenshot: {
          name: 'customization',
          alt: 'Personalización con la vista previa en vivo',
        },
      },
      {
        id: 'ajusta',
        heading: 'Ajusta a mano',
        paragraphs: [
          'Elige una fuente, pega la URL de tu logo y escoge el color de tu marca. A partir de ese color Mappi define los botones, el tono al pasar el cursor, un texto legible, los enlaces y el borde de los campos con foco. La vista previa muestra lo que verán las personas.',
        ],
      },
      {
        id: 'guarda',
        heading: 'Guarda',
        paragraphs: [
          'Haz clic en Guardar para aplicar los estilos a todos los cuestionarios de la cuenta. Restablecer solo devuelve los valores por defecto en pantalla hasta que guardes.',
        ],
      },
    ],
  },
  'users-and-roles': {
    title: 'Usuarios y roles',
    summary:
      'Invita a tu equipo, elige quién puede hacer cambios y define el idioma y los límites de la cuenta.',
    sections: [
      {
        id: 'roles',
        heading: 'Roles',
        paragraphs: [
          'Los usuarios Administrador tienen acceso total, incluido crear otros usuarios. Los de Solo lectura pueden verlo todo pero no hacer cambios. El propietario es el usuario que creó la cuenta y siempre tiene acceso total.',
        ],
        screenshot: {name: 'users', alt: 'Los usuarios de la cuenta'},
      },
      {
        id: 'agregar',
        heading: 'Agrega un usuario',
        paragraphs: [],
        steps: [
          'Ve a Usuarios y haz clic en Nuevo usuario.',
          'Escribe el nombre completo, el correo y una contraseña de al menos 8 caracteres.',
          'Elige el rol: Administrador o Solo lectura.',
          'Haz clic en Crear usuario y comparte las credenciales con tu compañero.',
        ],
      },
      {
        id: 'perfil',
        heading: 'Configuración de la cuenta',
        paragraphs: [
          'En Perfil → Configuración, elige el idioma de la cuenta (cambia los correos y las pantallas de quien responde, no la consola), el máximo de archivos por pregunta y tus píxeles de seguimiento (Meta, LinkedIn y Google Ads).',
        ],
      },
    ],
  },
  'system-settings': {
    title: 'Servidor de correo, OpenAI y analítica',
    summary:
      'Envía los correos de la cuenta por tu propio servidor, cobra la IA a tu clave de OpenAI y recibe los eventos de la cuenta en tu propio servicio.',
    sections: [
      {
        id: 'donde',
        heading: 'La pestaña Sistema',
        paragraphs: [
          'Abre Perfil → Sistema. Cada bloque indica si la cuenta usa su propio servicio («Tu servidor», «Tu clave») o el de Mappi. Las contraseñas y claves se guardan cifradas y solo se muestran sus últimos 4 caracteres.',
        ],
        screenshot: {
          name: 'profile-system',
          alt: 'La pestaña Sistema de Perfil',
        },
      },
      {
        id: 'smtp',
        heading: 'Servidor de correo (SMTP)',
        paragraphs: [
          'Los recordatorios, el estado de los seguimientos y los correos de corrección se pueden enviar por tu propio servidor. Escribe el servidor, el puerto, el cifrado, el usuario, la contraseña y el remitente, y haz clic en Validar: Mappi envía un correo de prueba y te dice qué falló si no pudo. Usa el servidor de Mappi para volver.',
        ],
      },
      {
        id: 'openai',
        heading: 'Clave de API de OpenAI',
        paragraphs: [
          'Las funciones de IA (el chat, la generación de cuestionarios, los tableros y la evaluación de respuestas abiertas) funcionan con OpenAI. Con tu propia clave se cobran a tu cuenta de OpenAI; sin ella se usa la clave de Mappi. Quitar mi clave vuelve a la de Mappi.',
        ],
      },
      {
        id: 'analitica',
        heading: 'Servicio de analítica',
        paragraphs: [
          'Cada evento de la cuenta (cuestionarios creados, respuestas, inicios de sesión…) se envía a un servicio de analítica. Define tu propio endpoint y tu clave de API para recibirlos en {URL base}/events; sin ellos se usa el servicio de Mappi.',
        ],
      },
    ],
  },
};
