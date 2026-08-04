let editorInstance;

ClassicEditor
  .create(document.querySelector('#editor'), {
    toolbar: {
      items: [
        'heading', '|',
        'fontSize', 'fontFamily', '|',
        'alignment', '|',
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'subscript', 'superscript', '|',
        'link', 'blockQuote', 'code', 'codeBlock', '|',
        'bulletedList', 'numberedList', 'todoList', '|',
        'imageUpload', 'insertTable', 'tableColumn', 'tableRow', 'mergeTableCells', '|',
        'highlight', 'horizontalLine', 'pageBreak', '|',
        'removeFormat', 'sourceEditing', 'undo', 'redo'
      ]
    },
    language: 'es',
    image: {
      toolbar: [
        'imageTextAlternative',
        'imageStyle:alignLeft', 'imageStyle:full', 'imageStyle:alignRight', 'imageStyle:side'
      ]
    },
    table: {
      contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
    },
    alignment: {
      options: ['left', 'center', 'right', 'justify']
    },
    extraPlugins: [function temporaryUploadAdapterPlugin(editor) {
      editor.plugins.get('FileRepository').createUploadAdapter = loader => {
        return {
          upload: () => loader.file.then(file => new Promise(resolve => {
            const reader = new FileReader();
            reader.onload = () => resolve({ default: reader.result });
            reader.readAsDataURL(file);
          })),
          abort: () => {}
        };
      };
    }],
    htmlSupport: {
      allow: [
        {
          name: /.*/,
          attributes: true,
          classes: true,
          styles: true
        }
      ]
    }
  })
  .then(editor => {
    editorInstance = editor;
  })
  .catch(error => {
    console.error(error);
  });



  function downloadPDF(ruta_img, namePAC, nameModulo, idtipo = 0) {
    // 👈 MODIFICADO: obtenemos el contenido fijo y el del editor
    const contenidoFijo = document.getElementById('contenido-fijo').innerHTML;
    const notas = editorInstance.getData();
  
    // 1) Construye el contenedor HTML
    const container = document.createElement('div');
    container.className = 'pdf-wrapper';
  
    // 👈 MODIFICADO: unimos contenido fijo y notas del editor
    container.innerHTML = `
      <div class="pdf-page">
        <div class="contenido">
          <h2 class="text-start">${nameModulo}</h2>
          ${contenidoFijo}
          <hr>
          <p><b>Notas adicionales:</b></p>
          ${notas}
        </div>
      </div>
    `;
  
    // 2) Inyecta estilos CSS
    const style = document.createElement('style');
  
    if (idtipo == 1) {
      style.textContent = `
        .pdf-wrapper {
          font-size: 14px;
          line-height: 1.4;
        }
        .pdf-page {
          box-sizing: border-box;
          padding: 0;
          background: white;
          page-break-after: always;
        }
        .pdf-page:last-child {
          page-break-after: auto;
        }
  
        .contenido {
          position: relative;
          z-index: 1;
        }
  
        .pdf-page table {
          width: 100%;
          page-break-inside: auto;
          border: 0.2px solid #d1d1d1;
        }
  
        .pdf-page tr,
        .pdf-page th,
        .pdf-page td {
          border: 0.2px solid #d3d3d3;
          padding: 5px;
        }
  
        .pdf-page tr {
          page-break-inside: avoid;
        }
  
        th {
          background-color: #f6f6f6;
          text-align: center;
          vertical-align: middle;
        }
  
        td {
          text-align: center;
          vertical-align: middle;
        }
  
        .pdf-page h1,
        .pdf-page h2,
        .pdf-page p,
        .pdf-page ul,
        .pdf-page ol {
          page-break-inside: avoid;
          page-break-after: auto;
        }
  
        .text-left {
          text-align: left;
        }
        .text-center {
          text-align: center;
        }
        .text-right {
          text-align: right;
        }
        .text-justify {
          text-align: justify;
        }
  
        .align-middle {
          vertical-align: middle;
        }
      `;
    } else {
      style.textContent = `
        .pdf-wrapper {
          font-size: 14px;
          line-height: 1.4;
        }
        .pdf-page {
          box-sizing: border-box;
          padding: 0;
          background: white;
          page-break-after: always;
        }
        .pdf-page:last-child {
          page-break-after: auto;
        }
  
        .contenido {
          position: relative;
          z-index: 1;
        }
  
        .pdf-page table {
          width: 100%;
          page-break-inside: auto;
          border: 0.2px solid #d1d1d1;
        }
  
        .pdf-page tr,
        .pdf-page th,
        .pdf-page td {
          border: 0.2px solid #d3d3d3;
          padding: 5px;
        }
  
        .pdf-page tr {
          page-break-inside: avoid;
        }
  
        th {
          background-color: #f6f6f6;
          text-align: center;
          vertical-align: middle;
        }
  
        .pdf-page h1,
        .pdf-page h2,
        .pdf-page p,
        .pdf-page ul,
        .pdf-page ol {
          page-break-inside: avoid;
          page-break-after: auto;
        }
  
        .text-left {
          text-align: left;
        }
        .text-center {
          text-align: center;
        }
        .text-right {
          text-align: right;
        }
        .text-justify {
          text-align: justify;
        }
  
        .align-middle {
          vertical-align: middle;
        }
  
        img {
          width: 50% !important;
          height: auto !important;
          display: block;
          margin: 0 auto;
        }
      `;
    }
  
    container.appendChild(style);
  
    // 3) Opciones de html2pdf
    const opt = {
      margin: [1, 1],
      filename: `${namePAC}.pdf`,
      image: { type: 'jpeg', quality: 0.95 },
      html2canvas: { scale: 4, useCORS: true, scrollY: 0 },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
  
    // 4) Genera el PDF y añade marca de agua
    html2pdf()
      .set(opt)
      .from(container)
      .toPdf()
      .get('pdf')
      .then(pdf => {
        const totalPages = pdf.internal.getNumberOfPages();
        const img = new Image();
        img.src = `${ruta_img}logo-clinica.png`;
        img.onload = () => {
          const cw = img.width, ch = img.height;
          const wmCanvas = document.createElement('canvas');
          wmCanvas.width = cw;
          wmCanvas.height = ch;
          const wctx = wmCanvas.getContext('2d');
          wctx.globalAlpha = 0.2;
          wctx.drawImage(img, 0, 0);
          const watermarkDataUrl = wmCanvas.toDataURL('image/png');
  
          const pageW = pdf.internal.pageSize.getWidth();
          const w = pageW * 0.5;
          const h = w * (ch / cw);
          const x = (pageW - w) / 2;
          const y = (pdf.internal.pageSize.getHeight() - h) / 2;
  
          for (let i = 1; i <= totalPages; i++) {
            pdf.setPage(i);
            pdf.addImage(watermarkDataUrl, 'PNG', x, y, w, h);
          }
  
          if (totalPages > 1) {
            pdf.deletePage(pdf.internal.getNumberOfPages());
          }
  
          pdf.save(`${namePAC}.pdf`);
        };
      });
  }
  