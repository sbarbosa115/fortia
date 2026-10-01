import type {GuideId, GuideText} from '../model/types';

/** Las 15 guías de la documentación en español (PRD §10.18). Mismos ids, orden y temas que content/en.ts. */
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
          'Mappi convierte cuestionarios en acciones. Diseñas un cuestionario (muchas veces con ayuda de la IA), lo envías a las personas que deben responderlo y Mappi convierte sus respuestas en resultados: una puntuación y un nivel, una recomendación de productos, un perfil o un simple mensaje de agradecimiento.',
          'Todo sucede en la consola. Quien responde nunca necesita una cuenta: abre un enlace, responde y ve su resultado.',
        ],
      },
      {
        id: 'menu',
        heading: 'El menú lateral',
        paragraphs: [
          'El menú agrupa la consola en tres bloques. Diseño reúne Experiencia IA (la página de inicio), Experiencia de diseño, Cuestionarios y Personalización. Enviar y seguir reúne Organizaciones, Asignaciones y Proyectos. Configuración reúne Usuarios, Integraciones, Perfil y esta Documentación.',
          'Abajo encuentras el selector de idioma, el bloque de tu cuenta con tu plan y Cerrar sesión. El selector de idioma solo cambia el idioma de la consola; el idioma de los correos y de las pantallas de quien responde es el idioma de tu cuenta, en Perfil.',
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
          'Crea un cuestionario en Experiencia IA o desde Cuestionarios → Nuevo cuestionario.',
          'Publícalo y comparte su enlace, o asígnalo a los miembros de una organización.',
          'Sigue las respuestas a medida que llegan en Respuestas.',
          'Lee el tablero para entender los resultados y actuar.',
        ],
      },
      {
        id: 'roles',
        heading: 'Usuarios de solo lectura',
        paragraphs: [
          'Si tu rol es de solo lectura puedes verlo todo, pero los botones que crean o cambian algo están deshabilitados. Pasa el cursor sobre un botón deshabilitado para leer el motivo.',
        ],
        tip: 'Tu plan también define algunos límites: cuando una acción no está incluida, el botón lo indica y enlaza a los planes.',
      },
    ],
  },
  'first-questionnaire': {
    title: 'Crea tu primer cuestionario',
    summary:
      'Elige un tipo, escribe los detalles, agrega preguntas y decide qué ven las personas al final, en tres pasos.',
    sections: [
      {
        id: 'elige-un-tipo',
        heading: 'Elige un tipo',
        paragraphs: [
          'Ve a Cuestionarios y haz clic en Nuevo cuestionario. Mappi ofrece cuatro tipos: Regular para encuestas clásicas que terminan con un mensaje de agradecimiento, Diagnóstico para puntuar a cada persona y ubicarla en niveles, Quiz Funnel para recomendar productos de tu tienda y Encadenado para generar un cuestionario a la medida a partir de las primeras respuestas y tus instrucciones.',
          'Un tipo que tu plan no incluye aparece deshabilitado, con el motivo.',
        ],
        screenshot: {
          name: 'questionnaire-new',
          alt: 'Los cuatro tipos de cuestionario para elegir',
        },
      },
      {
        id: 'detalles',
        heading: 'Paso 1: detalles',
        paragraphs: [
          'Escribe un título (obligatorio) y, si quieres, un enlace personalizado (slug): letras minúsculas, números y guiones. Si dejas el slug vacío, Mappi lo genera a partir del título. También puedes activar una página de bienvenida y un aviso que la persona debe leer primero.',
        ],
      },
      {
        id: 'preguntas',
        heading: 'Paso 2: preguntas',
        paragraphs: [
          'Agrega preguntas y agrúpalas por categoría. Cada pregunta tiene un título, un tipo de respuesta (selección única o múltiple, lista, ranking, texto, audio, rango, archivo o un mensaje) y puede ser obligatoria. Arrastra las preguntas para reordenarlas o moverlas a otra categoría.',
        ],
        tip: 'Las preguntas de texto y audio pueden hacer hasta cinco repreguntas cuando una respuesta no cumple tus criterios de aceptación.',
      },
      {
        id: 'final',
        heading: 'Paso 3: el final',
        paragraphs: [
          'Decide qué ve la persona al terminar: un mensaje de agradecimiento, una llamada a la acción con un botón o un formulario para capturar sus datos. En un diagnóstico, en cambio, defines los niveles, de 0 a la puntuación máxima y sin huecos.',
          'Nada se guarda hasta que haces clic en Crear y confirmas. Después puedes copiar el enlace, ver el cuestionario o seguir editando.',
        ],
      },
    ],
  },
  'create-with-ai': {
    title: 'Crea un cuestionario con IA',
    summary:
      'Describe lo que necesitas en Experiencia IA y deja que Mappi redacte, ajuste y cree el cuestionario contigo.',
    sections: [
      {
        id: 'inicia-un-chat',
        heading: 'Inicia un chat',
        paragraphs: [
          'Experiencia IA es la página de inicio de la consola. Escribe lo que quieres crear —por ejemplo, «un diagnóstico de madurez digital para tiendas pequeñas, 8 preguntas»— y presiona Enter. Mayúsculas+Enter agrega un salto de línea.',
          'El asistente responde con un borrador que aparece en la vista previa en vivo a la derecha, donde puedes responder las preguntas como lo haría una persona.',
        ],
        screenshot: {
          name: 'ai-experience',
          alt: 'Experiencia IA con el chat y la vista previa en vivo',
        },
      },
      {
        id: 'ajusta',
        heading: 'Ajusta el borrador',
        paragraphs: [
          'Pide cambios con tus palabras: agrega una pregunta, cambia el tono, tradúcelo, usa una escala de 1 a 10. Cuando el asistente ofrece opciones, aparecen como botones de respuesta rápida.',
        ],
        tip: 'Una conversación admite hasta 40 mensajes. Haz clic en Nuevo chat para empezar de nuevo.',
      },
      {
        id: 'crealo',
        heading: 'Créalo',
        paragraphs: [
          'Cuando el borrador te guste, pídele al asistente que lo cree. Aparece una tarjeta «Cuestionario creado» con botones para editarlo o verlo. Desde ahí es un cuestionario normal: puedes editarlo, compartirlo y leer sus respuestas.',
          'El asistente también puede hacer otras tareas por ti, como listar tus organizaciones o consultar el uso de tu plan. Cada turno cuenta para la función de chat de tu plan.',
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
          'Cuestionarios lista todos los cuestionarios de tu cuenta. Busca por título (⌘/Ctrl+K lleva al buscador), filtra por tipo y estado y ordena por fecha de creación o de actualización. Cada fila muestra el tipo, el número de preguntas y un interruptor de Activo.',
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
          'Solo los cuestionarios activos aceptan respuestas. Apaga el interruptor para dejar de recibir respuestas sin borrar nada; enciéndelo de nuevo cuando quieras.',
        ],
      },
      {
        id: 'editar',
        heading: 'Editar',
        paragraphs: [
          'Haz clic en Editar para abrir los mismos tres pasos que usaste al crearlo. No hay guardado automático: haz clic en Guardar cambios al terminar.',
        ],
      },
      {
        id: 'bloqueado',
        heading: 'Cuestionarios bloqueados',
        paragraphs: [
          'Cuando un cuestionario tiene respuestas queda bloqueado, para que las respuestas sigan correspondiendo a las preguntas que se respondieron. Para cambiarlo, haz clic en Crear una copia: obtienes un cuestionario nuevo sin respuestas, listo para editar.',
        ],
        tip: 'Las copias conservan las preguntas, el final y la configuración, con un enlace nuevo.',
      },
    ],
  },
  'share-and-collect': {
    title: 'Comparte un cuestionario y recibe respuestas',
    summary:
      'Publica un cuestionario, comparte su enlace público y sigue cada respuesta a medida que llega.',
    sections: [
      {
        id: 'enlace-publico',
        heading: 'El enlace público',
        paragraphs: [
          'Cada cuestionario tiene un enlace público construido con su slug. Cópialo desde el listado (Copiar enlace) o desde la pantalla de éxito al crearlo, y compártelo por correo, en tu sitio web o en redes sociales. Cualquiera con el enlace puede responder mientras el cuestionario esté activo.',
        ],
      },
      {
        id: 'pruebalo',
        heading: 'Pruébalo primero',
        paragraphs: [
          'Abre el enlace tú mismo antes de compartirlo: respóndelo como lo haría una persona y revisa la pantalla de resultado. Tus respuestas de prueba aparecen en Respuestas como cualquier otra.',
        ],
      },
      {
        id: 'respuestas',
        heading: 'Sigue las respuestas',
        paragraphs: [
          'Abre Respuestas desde la fila del cuestionario. Cada sesión muestra cuándo empezó, el nombre y el correo si la persona los dejó, el progreso y el estado: Llenando, Diligenciado, Procesando o Completado. Haz clic en Ver para leer cada respuesta con el tiempo dedicado y el resultado que recibió la persona.',
        ],
        screenshot: {
          name: 'answers',
          alt: 'Las respuestas de un cuestionario',
        },
      },
      {
        id: 'archivos',
        heading: 'Archivos y audio',
        paragraphs: [
          'Las imágenes, los PDF, el audio y el video que suben las personas se pueden previsualizar en el detalle de la respuesta, y cualquier archivo se puede descargar.',
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
          'Una organización es una empresa, un cliente o un equipo cuyos miembros responderán tus cuestionarios. Las asignaciones y los proyectos siempre se hacen para una organización.',
        ],
        screenshot: {
          name: 'organizations',
          alt: 'Las tarjetas de organizaciones',
        },
      },
      {
        id: 'crear',
        heading: 'Crea una organización',
        paragraphs: [
          'Haz clic en Nueva organización y escribe su nombre. El dominio de correo es opcional: si lo defines, Mappi te avisa de los miembros cuyo correo usa otro dominio, pero los guarda igual.',
        ],
      },
      {
        id: 'miembros',
        heading: 'Agrega miembros',
        paragraphs: [
          'Cada miembro necesita un nombre y al menos un correo o un teléfono; el cargo y el área son opcionales y te permiten enviar una asignación solo a una parte de la organización. Los nombres se guardan sin tildes y en minúsculas para que las búsquedas siempre los encuentren.',
        ],
      },
      {
        id: 'csv',
        heading: 'Importa desde CSV',
        paragraphs: [
          'Descarga la plantilla, llena una fila por miembro e impórtala. El archivo puede usar comas o punto y coma, y las columnas pueden estar en inglés o en español (name/nombre, email/correo, phone/telefono, role/rol, area/área). Las filas que no se pueden importar se listan con el motivo.',
        ],
        tip: 'Eliminar una organización elimina sus miembros. No se permite mientras asignaciones o proyectos la usen.',
      },
    ],
  },
  'assignations': {
    title: 'Envía cuestionarios con asignaciones',
    summary:
      'Asigna un cuestionario a los miembros de una organización, elige quién responde y envía recordatorios.',
    sections: [
      {
        id: 'tipos',
        heading: 'Por defecto y seguimiento',
        paragraphs: [
          'Una asignación por defecto le da a cada miembro su propia sesión. Una asignación de seguimiento se responde en conjunto en una sola sesión compartida, y quienes la responden reciben un recordatorio diario hasta completarla; después revisas cada respuesta y puedes devolverla para corrección.',
        ],
      },
      {
        id: 'crear',
        heading: 'Crea una asignación',
        steps: [
          'Ve a Asignaciones y haz clic en Nueva.',
          'Elige el tipo, la organización y quién responde: todos, algunas personas, un área o un cargo.',
          'Escoge el cuestionario y ponle un nombre a la asignación.',
          'Decide qué datos de registro llena la persona (el correo o el teléfono debe ser obligatorio).',
          'Guarda y copia el enlace para compartirlo.',
        ],
        paragraphs: [],
        screenshot: {
          name: 'assignations',
          alt: 'El listado de asignaciones',
        },
      },
      {
        id: 'progreso',
        heading: 'Sigue el progreso',
        paragraphs: [
          'El listado muestra cuántas personas o preguntas están completas. Abre una asignación para ver quién la completó y quién está pendiente, exportar la lista a CSV o enviar un recordatorio por correo.',
        ],
        tip: 'Un cuestionario pertenece a una sola organización. Si asignas uno que ya está asignado a otra, Mappi asigna una copia.',
      },
    ],
  },
  'projects': {
    title: 'Proyectos',
    summary:
      'Agrupa las asignaciones de seguimiento de una organización bajo una fecha límite y mira de un vistazo qué necesita tu atención.',
    sections: [
      {
        id: 'que-es',
        heading: 'Qué es un proyecto',
        paragraphs: [
          'Un proyecto agrupa asignaciones de seguimiento de una organización con una fecha límite. El listado te dice su estado —sin empezar, en progreso, requiere tu revisión, en corrección, completado o vencido— y el siguiente paso.',
        ],
        screenshot: {
          name: 'projects',
          alt: 'El listado de proyectos con sus pestañas',
        },
      },
      {
        id: 'asistente',
        heading: 'Crea un proyecto en tres pasos',
        paragraphs: [
          'Nuevo proyecto te guía por las preguntas (redáctalas con IA o escoge un cuestionario existente), la organización y su audiencia, y el proyecto con su fecha límite. Nada se guarda hasta que haces clic en Crear; entonces Mappi crea el cuestionario, la asignación y el proyecto a la vez.',
        ],
      },
      {
        id: 'revision',
        heading: 'Revisa y corrige',
        paragraphs: [
          'Abre un proyecto para ver cada asignación con sus respuestas y su estado de revisión. Aprueba o rechaza cada respuesta con un comentario y envía las rechazadas a corrección: las personas reciben un nuevo intento por correo.',
        ],
        tip: 'Usa las pestañas (Por revisar, En corrección, Vencidos…) para ver solo los proyectos que te necesitan.',
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
          'Haz clic en Analítica en la fila de un cuestionario. La primera vez, Mappi elige la mejor gráfica para cada pregunta, lo que puede tardar hasta medio minuto.',
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
          'Las tarjetas superiores muestran la tasa de finalización (sesiones completadas sobre todas las iniciadas), el número de sesiones, el tiempo promedio para completar y la pregunta donde más personas dejan de responder.',
        ],
      },
      {
        id: 'embudo',
        heading: 'El embudo',
        paragraphs: [
          'El embudo va de Iniciadas, pasando por cada pregunta, hasta Completadas, para que veas exactamente dónde se van las personas. Los tramos largos sin abandono se agrupan para mantenerlo corto.',
        ],
      },
      {
        id: 'graficas',
        heading: 'Gráficas por pregunta',
        paragraphs: [
          'Cada pregunta tiene su propia gráfica: distribuciones para las opciones, histogramas e indicadores para las escalas, y un desglose NPS para escalas de 0 a 10 (detractores 0–6, pasivos 7–8, promotores 9–10).',
        ],
        tip: 'El resumen es gratuito en todos los planes; las gráficas necesitan la función de tableros.',
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
          'Respuestas muestra por defecto las sesiones completadas. Cambia el filtro de estado para ver las que aún se están llenando, alterna las fechas entre tu hora local y UTC y elige cuántas filas cargar por página.',
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
          'Cada sesión muestra los datos de la persona, el tiempo total, cada pregunta con su respuesta y el tiempo dedicado, y el resultado que recibió: el nivel y la puntuación de un diagnóstico, los productos recomendados o el perfil generado.',
        ],
      },
      {
        id: 'exportar',
        heading: 'Exporta a una hoja de cálculo',
        paragraphs: [
          'Usa Exportar a Google Sheets para enviar todas las respuestas a una hoja de cálculo nueva. En una asignación también puedes exportar a CSV la lista de personas con su estado.',
        ],
        tip: 'La API externa entrega las mismas respuestas a tus propios sistemas; consulta la guía de claves de API y webhooks.',
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
          'En Personalización, escribe la URL de tu sitio web y guarda: Mappi lee tu sitio y elige por ti tu logo, tus colores y tu fuente. Después puedes ajustarlo todo.',
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
          'Elige una de seis fuentes, pega la URL de tu logo y escoge el color de tu marca. A partir de ese color Mappi deriva los botones, el tono al pasar el cursor, un texto legible, los enlaces y el borde de los campos con foco. La vista previa muestra lo que verán las personas.',
        ],
      },
      {
        id: 'guardar',
        heading: 'Guarda',
        paragraphs: [
          'Haz clic en Guardar para aplicar los estilos a todos los cuestionarios de la cuenta. Restablecer solo vuelve a los valores por defecto en pantalla hasta que guardes.',
        ],
        tip: 'Guardar estilos requiere la función de estilos de tu plan.',
      },
    ],
  },
  'api-and-webhooks': {
    title: 'Claves de API y webhooks',
    summary:
      'Conecta Mappi con tus propios sistemas: lee cuestionarios y respuestas con la API y recibe cada respuesta completada con un webhook.',
    sections: [
      {
        id: 'claves-de-api',
        heading: 'Claves de API',
        paragraphs: [
          'En Integraciones → Claves de API, crea una clave con un nombre y una vigencia (7, 30, 60 o 90 días, o sin vencimiento). La clave se muestra una sola vez: cópiala y guárdala en un lugar seguro. Revócala cuando ya no la necesites.',
        ],
        screenshot: {
          name: 'integrations',
          alt: 'Integraciones con la pestaña de claves de API',
        },
      },
      {
        id: 'api-externa',
        heading: 'La API externa',
        paragraphs: [
          'Envía la clave en el encabezado X-API-Key para listar tus cuestionarios y leer las respuestas de cada uno, de la más reciente a la más antigua. La pestaña de referencia de la API tiene ejemplos de curl listos para copiar.',
        ],
      },
      {
        id: 'webhooks',
        heading: 'Webhooks',
        paragraphs: [
          'Un webhook llama a una URL tuya (solo https) cada vez que se completa una respuesta, con las respuestas en el cuerpo. Agrégalo en Integraciones → Webhooks; la referencia de entrega muestra los encabezados y un ejemplo del contenido para que verifiques cada llamada.',
        ],
        tip: 'La API y los webhooks son funciones del plan: sus pestañas te avisan cuando tu plan no las incluye.',
      },
    ],
  },
  'store-quiz-funnel': {
    title: 'Recomienda productos con un quiz funnel',
    summary:
      'Conecta tu tienda o lee tu sitio web y genera un quiz que recomienda el producto adecuado al final.',
    sections: [
      {
        id: 'tienda',
        heading: 'Paso 1: tu tienda',
        paragraphs: [
          'Crea un Quiz Funnel desde Nuevo cuestionario. Si tu tienda funciona con Shopify, autoriza la conexión y carga tus productos. Si no, escribe la URL de tu tienda y elige cuántos productos leer (5, 10, 20 o 30).',
        ],
      },
      {
        id: 'productos',
        heading: 'Paso 2: productos',
        paragraphs: [
          'Revisa los productos que encontró Mappi y quita los que no quieras recomendar. Cargarlos es opcional: Generar lee tu sitio web si te saltas este paso.',
        ],
      },
      {
        id: 'generar',
        heading: 'Paso 3: genera',
        paragraphs: [
          'Elige el tipo de quiz —una experiencia o un perfilamiento— y haz clic en Generar. En unos minutos obtienes un cuestionario cuyo resultado recomienda productos de tu catálogo, con su enlace y su precio.',
        ],
        tip: 'Con Shopify, activa el bloque de Mappi en el editor de tu tema para que el quiz aparezca en tu tienda.',
      },
    ],
  },
  'users-and-roles': {
    title: 'Usuarios y roles',
    summary:
      'Invita a tu equipo, elige quién puede hacer cambios y entiende qué puede hacer la persona dueña de la cuenta.',
    sections: [
      {
        id: 'roles',
        heading: 'Roles',
        paragraphs: [
          'Los usuarios Administrador tienen acceso completo, incluso para crear otros usuarios. Los usuarios de solo lectura pueden verlo todo, pero no hacer cambios. La persona dueña es el usuario que creó la cuenta y siempre tiene acceso completo.',
        ],
        screenshot: {name: 'users', alt: 'Los usuarios de la cuenta'},
      },
      {
        id: 'agregar',
        heading: 'Agrega un usuario',
        steps: [
          'Ve a Usuarios y haz clic en Nuevo usuario.',
          'Escribe el nombre completo, el correo y una contraseña de al menos 8 caracteres.',
          'Elige el rol: Administrador o Solo lectura.',
          'Haz clic en Crear y comparte las credenciales con tu compañero.',
        ],
        paragraphs: [],
      },
      {
        id: 'perfil',
        heading: 'Configuración de la cuenta',
        paragraphs: [
          'En Perfil → Configuración, elige el idioma de la cuenta (cambia los correos y las pantallas de quien responde, no la consola), el máximo de archivos por pregunta y tus píxeles de seguimiento.',
        ],
        tip: 'El número de usuarios depende de tu plan.',
      },
    ],
  },
  'plans-and-billing': {
    title: 'Planes, uso y facturación',
    summary:
      'Mira cuánto de tu plan estás usando, cambia de plan y administra tu suscripción y tus facturas.',
    sections: [
      {
        id: 'uso',
        heading: 'Tu uso',
        paragraphs: [
          'Perfil → Plan y uso muestra tu plan y una barra por cada límite: cuestionarios, respuestas y cada función. Las barras se vuelven ámbar al 75 % y rojas al 90 %. Cuando llegas a la mitad de un límite, un aviso te lo recuerda en la parte superior de la consola.',
        ],
      },
      {
        id: 'cambiar',
        heading: 'Cambia de plan',
        paragraphs: [
          'Abre Planes para compararlos. Suscríbete, mejora o cambia a un plan menor, mensual o anual; el precio anual muestra cuánto ahorras. Los códigos promocionales se aplican al pagar.',
        ],
        screenshot: {name: 'plans', alt: 'Las tarjetas de los planes'},
      },
      {
        id: 'facturacion',
        heading: 'Facturación',
        paragraphs: [
          'Administrar facturación abre el portal de pagos, donde actualizas tu tarjeta y descargas tus facturas. Puedes cancelar la suscripción cuando quieras y reanudarla antes de que termine.',
        ],
        tip: 'Planes nunca se bloquea: siempre puedes abrir Planes, Proyectos y Asignaciones, incluso cuando llegas a un límite.',
      },
    ],
  },
};
