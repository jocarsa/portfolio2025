# Jose Vicente Carratalá Sanchis – Portafolio 3D

Experiencia **a pantalla completa** para presentar tu portafolio 3D:

* **HTML+CSS+JS** en un solo archivo (`index.html`)
* **Listado dinámico** de imágenes desde `portfolio-list.php` (PHP)
* **Ken Burns** aleatorio (pan/zoom con puntos de inicio/fin no forzados a esquinas)
* **Fundido cruzado**, **superposición arrastrable** con blur y metadatos
* **Autoplay** con barra de progreso, **teclas rápidas**, **orden aleatorio** de diapositivas

> Demo local: sirve el proyecto con PHP y abre `index.html`. El JS hace `fetch` al PHP para obtener el JSON con tus PNG.

---

## ✨ Características

* **Pantalla completa**: cada imagen ocupa el **100%** de la ventana (cover).
* **Transiciones suaves**: fundido cruzado + Ken Burns aleatorio (Web Animations API).
* **Autoplay** con indicador de progreso y botones **Siguiente/Anterior**.
* **Orden aleatorio** en cada carga y comienzo en una **lámina aleatoria**.
* **Overlay informativo** con **fondo difuminado** y **arrastrable** (drag & drop).
* **Metadatos integrados**: lee descripciones incrustadas en los PNG (tEXt/zTXt/iTXt).
* **Teclas rápidas**: ◀ ▶, **Barra espaciadora**, click en fondo.

---

## 📁 Estructura del proyecto

```
portfolio2025/
├─ index.html               # Frontend (HTML+CSS+JS en un solo archivo)
├─ portfolio-list.php       # Backend sencillo en PHP: lista PNG + metadatos
└─ portfolio/               # Coloca aquí tus .png (los listará automáticamente)
```

> Puedes añadir/actualizar PNG en `portfolio/` sin tocar el código.

---

## 🚀 Puesta en marcha

### Requisitos

* **PHP** 7.4+ (o superior)
* Navegador moderno

### Opción A: servidor PHP embebido (rápido)

```bash
php -S localhost:8000
# Entra en http://localhost:8000/index.html
```

### Opción B: Apache/Nginx

* Copia el proyecto a tu DocumentRoot (o configura un vhost)
* Asegúrate de que `portfolio/` es accesible y que PHP ejecuta `portfolio-list.php`

---

## 🖼️ Añadir imágenes y descripciones

Coloca tus **PNG** en la carpeta `portfolio/`. El PHP devuelve un JSON con:

```json
[
  { "src": "/portfolio/tu-imagen.png", "title": "...", "desc": "...", "mtime": 1731436800, "size": "1234567", "dim": "1920×1080" }
]
```

### ¿Cómo incrusto una descripción dentro del PNG? (Ubuntu Linux)

#### Opción 1: **GIMP** (GUI)

1. Abre la imagen → *Archivo → Exportar como…* → PNG → *Exportar*
2. En el diálogo de exportación activa **“Guardar comentario”** y escribe tu descripción

> Nuestro PHP lee `Comment`, `Description`, `Title`, etc. (tEXt / iTXt / zTXt).

#### Opción 2: **exiftool** (terminal, lote)

```bash
sudo apt-get update && sudo apt-get install -y libimage-exiftool-perl
exiftool -overwrite_original -Description="Render de catedral con GI" portfolio/scene01.png
exiftool -Description -Comment -Title portfolio/scene01.png
```

#### Opción 3: **ImageMagick** (comentar)

```bash
sudo apt-get install -y imagemagick
mogrify -comment "Nebulosa volumétrica – Cycles 2048 spp" portfolio/scene02.png
```

> También funcionan **Krita**, **digiKam**, **XnView MP** (escriben XMP/iTXt/tEXt).
> Si alguna app guarda en sidecar/base de datos (p. ej. gThumb/Shotwell), el servidor **no** verá la descripción.

---

## 🔧 Configuración rápida

Edita estos parámetros en `index.html` (al principio del `<script>`):

```js
const API = 'portfolio-list.php';   // ruta del PHP
let intervalMs = 14000;             // duración por lámina (Ken Burns + autoplay)
```

* Cambia `intervalMs` si quieres un pase más rápido/lento (valor en ms).
* El orden ya se **baraja automáticamente** y el inicio es **aleatorio**.

---

## ⌨️ Atajos y controles

* **◀ / ▶**: Anterior / Siguiente
* **Barra espaciadora**: activar/pausar **Auto**
* **Click** en el fondo: **Siguiente** (pausa el Auto)
* **Arrastrar** la cabecera del panel: mueve la ventana informativa

---

## 🧩 Cómo funciona

* `index.html`:

  * Hace `fetch` a `portfolio-list.php`
  * **Baraja** el array de imágenes (Fisher–Yates) y elige un **índice inicial aleatorio**
  * Pre-carga la siguiente imagen y **cruza capas** para el fundido
  * Aplica **Ken Burns** aleatorio con la **Web Animations API**
  * Rellena el overlay con **título**, **dimensiones**, **fecha** y **descripción** si existe

* `portfolio-list.php`:

  * Recorre `./portfolio/*.png`
  * Obtiene **dimensiones**, **mtime**, **size**
  * Lee **text chunks** PNG (tEXt, zTXt, iTXt) y usa `Description/Comment/Title/…` como `desc`
  * Devuelve un **JSON** listo para el frontend

---

## 🧪 Comprobaciones útiles

* ¿El JSON responde?
  Abre en el navegador: `http://localhost:8000/portfolio-list.php`

* ¿No se ve nada?

  * Asegúrate de **servir** el proyecto con PHP (no abras el HTML con `file://`)
  * Verifica que tienes **PNG** en `portfolio/`
  * Mira la consola del navegador (F12) para errores de red/JS

---

## 🛠️ Roadmap (ideas)

* Botón **“Rebarajar”** sin recargar página
* **Pausar** Ken Burns mientras el usuario arrastra el overlay
* Soporte para **sidecar JSON** por imagen (`scene01.json` con tags, software, passes…)
* **Captions** enriquecidos (negrita, enlaces, listas)
* Modo **“pantalla siempre encendida”** para exhibiciones

---

## 🤝 Contribuir

¡Se aceptan PRs!

1. Haz un fork del repo
2. Crea una rama: `git checkout -b feature/mi-mejora`
3. Commits claros: `feat: añade botón rebarajar`
4. Pull Request con descripción breve y captura si aplica

---

## 📝 Licencia

Recomendado: **MIT** (puedes cambiarlo).
Incluye un archivo `LICENSE` si quieres especificar derechos de uso.

---

## 👤 Autor

**Jose Vicente Carratalá Sanchis**
Repo: `https://github.com/jocarsa/portfolio2025`



