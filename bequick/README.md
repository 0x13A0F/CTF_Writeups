# Be Quick or Be dead

## Description

 Let's see if you're as QuickeR as you pretend to be.🧨
 
nc bequick.challs.shellmates.club 1337

**Category:** Misc

## Requirements

-  **nclib**
		pip install nclib
-  **zxing**
  - downlaod the 3 jar files from the repo (core, javase, jcommander)

- **imagemagick**
 - Arch: 
 
		pacman -S imagemagick
  - Debian
  
		apt install imagemagick

## Solution

I choosed to solve it with kind of autistic approach x)
the steps are as below:

1. Connect to the service using nclib
2. Printing the qrcode received from the service
3. Taking a screenshot of the whole screen
4. Decoding the qrcode using zxing (no need to crop the screenshot)
5. Sending the decoded output to the service

## Script

		import nclib,os,time
		nc = nclib.Netcat(("bequick.challs.shellmates.club",1337))
		res = nc.recv_until(b"Decoded string: ")
		# print the qrcode
		print(res.decode("utf-8"))
		# sleep and take screenshot
		time.sleep(0.1)
		os.system("import -window root qr.png")
		# decode qrcode
		os.system("java -cp 'zxing/core-3.3.4.jar:zxing/javase-3.3.4.jar:zxing/jcommander-1.72.jar' com.google.zxing.client.j2se.CommandLineRunner qr.png | head -3 | tail -1 > decoded.txt")
		with open("decoded.txt","r") as f: decoded=f.read()
		# send decoded output
		nc.send(decoded.encode("utf-8"))
		print(nc.recv())

## Result

![Result](https://www.commentfaireunfilm.com/wp-content/uploads/2019/12/result-3236285_960_720.jpg)
 
 ###Flag: 
 **shellmates{4lR1g|-|t_@lriGh7_1_tH1nK_y0u`R3_f4st_en0ugh}**
