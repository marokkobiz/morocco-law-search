from __future__ import annotations

import fitz


def list_embedded_fonts(pdf_path: str):
    """
    Return every embedded font found in a PDF.

    Does NOT decode anything yet.
    """

    doc = fitz.open(pdf_path)

    fonts = []

    seen = set()

    for page_number, page in enumerate(doc, start=1):

        for font in page.get_fonts(full=True):

            xref = font[0]

            if xref in seen:
                continue

            seen.add(xref)

            fonts.append(
                {
                    "page": page_number,
                    "xref": xref,
                    "basefont": font[3],
                    "subtype": font[2],
                    "encoding": font[5],
                }
            )

    return fonts

import re

BFCHAR_RE = re.compile(
    r"<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>"
)


def extract_tounicode_maps(pdf_path: str):
    """
    Extract ToUnicode mappings from every embedded font.
    """

    doc = fitz.open(pdf_path)

    result = {}

    for page in doc:

        for font in page.get_fonts(full=True):

            font_xref = font[0]

            if font_xref in result:
                continue

            obj = doc.xref_object(font_xref)

            m = re.search(r"/ToUnicode\s+(\d+)\s+0\s+R", obj)

            if not m:
                continue

            cmap_xref = int(m.group(1))

            stream = doc.xref_stream(cmap_xref)

            if not stream:
                continue

            text = stream.decode("latin1", errors="ignore")

            mapping = {}

            for src, dst in BFCHAR_RE.findall(text):
                mapping[int(src, 16)] = int(dst, 16)

            result[font_xref] = mapping

    return result