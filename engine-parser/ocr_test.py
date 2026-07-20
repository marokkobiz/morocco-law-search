import fitz
import pytesseract
from PIL import Image


pdf = "tests/arabic/مرسوم رقم 2.26.530 في شأن الساعة القانونية-1783329642436.pdf"

doc = fitz.open(pdf)

for i, page in enumerate(doc):
    print("PAGE", i+1)

    pix = page.get_pixmap(
        dpi=300
    )

    img = Image.frombytes(
        "RGB",
        [pix.width, pix.height],
        pix.samples
    )

    text = pytesseract.image_to_string(
        img,
        lang="ara",
        config="--psm 6"
    )

    print(text[:2000])
