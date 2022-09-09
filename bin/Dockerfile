FROM debian:11

RUN echo "deb http://deb.debian.org/debian bullseye-backports main contrib non-free" >> /etc/apt/sources.list \
	&& echo "deb-src http://deb.debian.org/debian bullseye-backports main contrib non-free" >> /etc/apt/sources.list \
	&& apt-get update \
	&& apt-get install -y \
		git \
		git-filter-repo \
		vim
