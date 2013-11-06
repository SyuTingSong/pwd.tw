#include <string.h>
#include <stdlib.h>
#include <kclangc.h>
#include <fcgi_stdio.h>
#include <xcrypt.h>

char * sha1 ( char * result, const char * str )
{
	SHA1_CTX * ctx;
	unsigned char buffer[20];
	int i;

	ctx = malloc(sizeof(SHA1_CTX));
	SHA1Init(ctx);
	SHA1Update(ctx, (unsigned char *) str, strlen(str));
	SHA1Final(buffer, ctx);

	for(i = 0; i < 20; i++)
		sprintf(&result[i*2], "%02x", buffer[i]);

	if(ctx != NULL) free(ctx);
}

const char * db_file = "/home/rek/pwd.kch";
char * pwd_get_pk(KCDB * db)
{
	char * pk, * result;
	size_t vsiz;
	int i;
	const char * map = ".adgjmptw";

	pk = kcdbget(db, "master", 6, &vsiz);
	if(pk)
	{
		result = malloc(vsiz + 2);
		strcpy(result, pk);
		kcfree(pk); pk = NULL;
	}
	else
	{
		result = malloc(3);
		strcpy(result, "0");
	}

	// increase pk
	vsiz = strlen(result);
	for(i = 0; ; i++)
	{
		if(i == vsiz)
		{
			result[i] = i == 0 ? '1' : (result[i - 1] == '1' ? '2' : '1');
			result[i + 1] = '\0';
			break;
		}
		result[i]++;
		if(result[i] >= '9')
			result[i] = i > 0 ? (result[i - 1] == '0' ? '1' : '0') : '1';
		else if(i > 0 && result[i - 1] == result[i])
			i--;
		else if(i < vsiz && result[i + 1] == result[i])
			i--;
		else
			break;
	}

	if(!kcdbset(db, "master", 6, result, strlen(result)))
	{
		free(result);
		return NULL;
	}

	// mapping result
	vsiz = strlen(result);
	for(i = 0 ; i < vsiz; i++)
	{
		result[i] = map[result[i]&0x0f];
	}
	return result;
}
char * pwd_save(KCDB * db, const char * origin_url)
{
	char * pk, * epk, * url;
	char hash[41];
	size_t vsiz;
	if(!kcdbopen(db, db_file, KCOWRITER|KCOCREATE))
	{
		printf("Status: 500 INTERNAL ERROR\r\n\r\nkcdb open error: %s\n", kcecodename(kcdbecode(db)));
		return NULL;
	}

	if(strstr(origin_url, "http://") == NULL && strstr(origin_url, "https://") == NULL)
	{
		url = malloc(strlen(origin_url) + 8);
		strcpy(url, "http://");
		strcat(url, origin_url);
	}
	else
	{
		url = malloc(strlen(origin_url) + 1);
		strcpy(url, origin_url);
	}

	// calc url hash and check if exists
	sha1(hash, url);
	epk = kcdbget(db, hash, strlen(hash), &vsiz);
	if(epk)
	{
		pk = malloc(strlen(epk) + 1);
		strcpy(pk, epk);
		kcfree(epk);
		free(url);
		kcdbclose(db);
		return pk;
	}

	pk = pwd_get_pk(db);
	if(pk == NULL)
	{
		free(url);
		kcdbclose(db);
		return NULL;
	}

	//TODO save pk => url && hash => pk
	if(!kcdbset(db, pk, strlen(pk), url, strlen(url)))
	{
		free(pk); pk = NULL;
	}
	if(!kcdbset(db, hash, strlen(hash), pk, strlen(pk)))
	{
		free(pk); pk = NULL;
	}

	free(url);
	kcdbclose(db);
	return pk;
}

char * pwd_load(KCDB * db, const char * key)
{
	char * url, * value;
	size_t vsiz;

	if(!kcdbopen(db, db_file, KCOREADER))
	{
		printf("Status: 500 INTERNAL ERROR\r\n\r\nkcdb open error: %s\n", kcecodename(kcdbecode(db)));
		return NULL;
	}

	value = kcdbget(db, key, strlen(key), &vsiz);
	if(!value)
	{
		kcdbclose(db);
		return NULL;
	}

	url = malloc(strlen(value) + 1);
	strcpy(url, value);
	kcfree(value);
	kcdbclose(db);
	return url;
}


int main()
{
	const char * host = "pwd.tw";
	const char * db_file = "/home/rek/pwd.kch";
	char * uri;
	char * server_name;
	char * server;
	KCDB * db;
	char * v;
	db = kcdbnew();

	while(FCGI_Accept() >= 0)
	{
		uri = getenv("REQUEST_URI");
		server_name = getenv("SERVER_NAME");

		if(server_name == NULL)
		{
			printf("Status: 404 Not Found\r\n\r\n");
			continue;
		}

		if(strcmp(server_name, host) == 0)
		{
			if(uri == NULL || strcmp(uri, "/") == 0)
			{
				printf("X-LIGHTTPD-send-file: /var/www/index.html\r\n\r\n");
			}
			else
			{
				v = pwd_save(db, &uri[1]);
				if(v)
				{
					printf("Content-Type: text/html\r\n"
						"Content-Length: %d\r\n\r\n"
						"http://%s.pwd.tw\r\n", strlen(v) + 16, v);
				}
				else
				{
					printf("Status: 500 SERVER ERROR\r\n\r\nkc error: %s\r\n", kcecodename(kcdbecode(db)));
				}
			}
		}
		else
		{
			server = malloc(strlen(server_name) + 1);
			strcpy(server, server_name);
			server[strlen(server) - strlen(host) - 1] = '\0';
			v = pwd_load(db, server);
			free(server);server = NULL;
			if(v)
			{
				printf("Status: 301 Moved Permanently\r\n"
					"Location: %s\r\n"
					"Content-Length: %d\r\n\r\n"
					"%s\n", v, strlen(v) + 1, v);
			}
			else
			{
				printf("Status: 404 Not Found\r\n"
					"Content-Type: text/plain\r\n\r\n"
					"The shorten %s cannot be found\r\n"
					"Debug Info: %s",
					server_name,
					kcecodename(kcdbecode(db))
				);
			}
		}
		if(v)
		{
			free(v);
			v = NULL;
		}
	}

	kcdbdel(db);
}

