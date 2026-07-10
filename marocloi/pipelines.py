# Define your item pipelines here
#
# Don't forget to add your pipeline to the ITEM_PIPELINES setting
# See: https://docs.scrapy.org/en/latest/topics/item-pipeline.html


# useful for handling different item types with a single interface
from itemadapter import ItemAdapter
import scrapy
from scrapy.pipelines.files import FilesPipeline
from urllib.parse import quote

class LawFilePipeline(FilesPipeline):
    def get_media_requests(self, item, info):
        # Properly encode the URL to handle spaces and special characters
        for file_url in item.get('file_urls', []):
            # Encode only the path part to avoid breaking the scheme/domain
            encoded_url = quote(file_url, safe=":/%?=&")
            
            # Yield a request that explicitly allows redirects
            yield scrapy.Request(
                encoded_url, 
                meta={'item': item},
                dont_filter=True  # Ensure the pipeline doesn't block the request
            )

    def file_path(self, request, response=None, info=None, *, item=None):
        # Retrieve the item from the request meta if it's not passed directly
        if item is None:
            item = request.meta.get('item')
        
        # Ensure title is safe for filenames
        title = item.get('title', 'default_filename')
        # Remove characters that are illegal in Windows/Linux filenames
        safe_title = "".join([c for c in title if c.isalnum() or c in (' ', '-', '_')]).strip()
        
        return f"{safe_title}.pdf"

