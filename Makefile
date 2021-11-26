build:
	docker build -t laravel-ide-helper .

bash:
	docker run --rm --volume "${PWD}:/opt/project" -w /opt/project -it laravel-ide-helper bash

install:
	docker run --rm --volume "${PWD}:/opt/project" -w /opt/project -it laravel-ide-helper composer install

test:
	docker run --rm --volume "${PWD}:/opt/project" -w /opt/project -it laravel-ide-helper composer test